<?php

namespace Modules\AI\Tests\Feature;

use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\FilamentEchoRegistrar;
use Modules\AI\Jobs\StreamAssistantTurnJob;
use Modules\AI\Tests\Unit\AITestCase;
use Modules\Core\Models\Branch;
use ReflectionObject;
use Spatie\Permission\Models\Role;

class AssistantTurnTest extends AITestCase
{
    public function test_turn_endpoint_dispatches_job_and_returns_accepted(): void
    {
        Bus::fake();

        $user = $this->assistantUser();
        $this->actingAs($user);

        $response = $this->postJson(route('ai.assistant.turn'), [
            'message' => 'Hello assistant',
            'conversation_id' => null,
        ]);

        $response->assertAccepted()
            ->assertJsonStructure(['turn_id', 'conversation_id']);

        Bus::assertDispatched(StreamAssistantTurnJob::class, function (StreamAssistantTurnJob $job) use ($user): bool {
            return (string) $job->userId === (string) $user->getAuthIdentifier()
                && $job->message === 'Hello assistant';
        });
    }

    public function test_turn_endpoint_requires_permission(): void
    {
        $user = $this->assistantUser(grantPermission: false);
        $this->actingAs($user);

        $this->postJson(route('ai.assistant.turn'), [
            'message' => 'Hello',
        ])->assertForbidden();
    }

    public function test_stream_job_resolves_concrete_auth_user_not_abstract_core_user(): void
    {
        $user = $this->assistantUser();

        $job = new StreamAssistantTurnJob(
            userId: $user->getAuthIdentifier(),
            message: 'Hello',
            turnId: (string) Str::uuid(),
        );

        $method = new \ReflectionMethod($job, 'resolveUser');
        $resolved = $method->invoke($job);

        $this->assertInstanceOf(User::class, $resolved);
        $this->assertTrue($resolved->is($user));
    }

    public function test_module_fills_filament_echo_when_host_has_no_key(): void
    {
        config([
            'ai.broadcasting.configure_filament_echo' => true,
            'ai.broadcasting.echo' => [
                'broadcaster' => 'reverb',
                'key' => 'ai-module-echo-key',
                'wsHost' => 'localhost',
            ],
            'filament.broadcasting.echo' => [
                'broadcaster' => 'reverb',
                'key' => null,
            ],
        ]);

        FilamentEchoRegistrar::register();

        $this->assertSame('ai-module-echo-key', config('filament.broadcasting.echo.key'));
        $this->assertSame('localhost', config('filament.broadcasting.echo.wsHost'));
    }

    public function test_module_does_not_override_host_filament_echo_key(): void
    {
        config([
            'ai.broadcasting.configure_filament_echo' => true,
            'ai.broadcasting.echo' => [
                'key' => 'ai-module-echo-key',
                'wsHost' => 'ai-host',
            ],
            'filament.broadcasting.echo' => [
                'key' => 'host-echo-key',
                'wsHost' => 'host.example',
            ],
        ]);

        FilamentEchoRegistrar::register();

        $this->assertSame('host-echo-key', config('filament.broadcasting.echo.key'));
        $this->assertSame('host.example', config('filament.broadcasting.echo.wsHost'));
    }

    public function test_user_channel_authorization(): void
    {
        $this->useReverbBroadcastAuth();

        $user = $this->assistantUser();
        $other = $this->assistantUser();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-ai.user.'.$user->id,
                'socket_id' => '1234.5678',
            ])
            ->assertSuccessful();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-ai.user.'.$other->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_conversation_channel_authorization(): void
    {
        $this->useReverbBroadcastAuth();

        $owner = $this->assistantUser();
        $other = $this->assistantUser();
        $conversationId = (string) Str::uuid();
        $table = config('ai.conversations.tables.conversations', 'agent_conversations');

        DB::table($table)->insert([
            'id' => $conversationId,
            'user_id' => $owner->id,
            'title' => 'Test conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-ai.conversation.'.$conversationId,
                'socket_id' => '1234.5678',
            ])
            ->assertSuccessful();

        $this->actingAs($other)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-ai.conversation.'.$conversationId,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    /**
     * .env.testing uses BROADCAST_CONNECTION=null. NullBroadcaster skips ACLs, and
     * channel callbacks are registered on the first resolved driver — so switch to
     * Reverb, purge cached drivers, and re-register channels before asserting auth.
     */
    protected function useReverbBroadcastAuth(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'testing-reverb-key',
            'broadcasting.connections.reverb.secret' => 'testing-reverb-secret',
            'broadcasting.connections.reverb.app_id' => 'testing-reverb-app',
        ]);

        $manager = app(BroadcastManager::class);
        (new ReflectionObject($manager))->getProperty('drivers')->setValue($manager, []);
        app()->forgetInstance(Broadcaster::class);

        require module_path('AI', 'routes/channels.php');
    }

    protected function assistantUser(bool $grantPermission = true): User
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('assistant_turn_user_'.uniqid(), 'web');

        if ($grantPermission) {
            $this->grantAssistantPermissions($role);
        }

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        if ($grantPermission) {
            $this->assertTrue($user->can(AssistantPermission::UseAssistant));
        }

        return $user;
    }
}

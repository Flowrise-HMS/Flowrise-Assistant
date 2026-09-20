<?php

namespace Modules\AI\Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AI\Models\AssistantAuditLog;
use Tests\TestCase;

class AiAssistantConfigTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assistant_config_is_available_under_the_key_the_services_read(): void
    {
        $this->assertIsBool(config('ai-assistant.phi_redaction.enabled'));
        $this->assertSame('REF', config('ai-assistant.phi_redaction.token_prefix'));
        $this->assertIsInt(config('ai-assistant.audit.retention_days'));
    }

    public function test_audit_prune_command_is_scheduled_daily(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn (Event $event): string => (string) $event->command)
            ->filter(fn (string $command): bool => str_contains($command, 'ai:prune-assistant-audit'));

        $this->assertCount(1, $commands);
    }

    public function test_audit_prune_command_deletes_rows_past_retention(): void
    {
        $this->migrateModules(['Core', 'AI']);
        config(['ai-assistant.audit.retention_days' => 30]);

        $user = User::factory()->create();
        $old = AssistantAuditLog::query()->create(['user_id' => $user->id, 'prompt_redacted' => 'old', 'provider' => 'gemini', 'model' => 'x']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();
        $recent = AssistantAuditLog::query()->create(['user_id' => $user->id, 'prompt_redacted' => 'recent', 'provider' => 'gemini', 'model' => 'x']);

        $this->artisan('ai:prune-assistant-audit')->assertSuccessful();

        $this->assertDatabaseMissing('assistant_audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('assistant_audit_logs', ['id' => $recent->id]);
    }
}

<?php

namespace Modules\AI\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\AI\Classes\Services\AssistantOrchestratorService;
use Modules\AI\Classes\Services\AssistantProposalExecutor;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Livewire\AssistantChatWidget;
use Modules\AI\Tests\Unit\AITestCase;
use Modules\Core\Models\Branch;
use Modules\Core\Settings\FeatureSettings;
use Spatie\Permission\Models\Role;

class AssistantChatWidgetTest extends AITestCase
{
    public function test_widget_is_hidden_without_assistant_permission(): void
    {
        $user = $this->assistantUser(grantPermission: false);
        $this->actingAs($user);

        Livewire::test(AssistantChatWidget::class)
            ->assertDontSee(__('Open FlowRise Assistant'));
    }

    public function test_widget_is_hidden_when_assistant_feature_is_disabled(): void
    {
        $user = $this->assistantUser();
        $settings = app(FeatureSettings::class);
        $settings->ai_assistant_enabled = false;
        $settings->save();

        $this->actingAs($user);

        Livewire::test(AssistantChatWidget::class)
            ->assertDontSee(__('Open FlowRise Assistant'));
    }

    public function test_widget_opens_compact_panel_and_expands(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        Livewire::test(AssistantChatWidget::class)
            ->assertSet('layout', 'closed')
            ->assertSeeHtml('flowrise-assistant__fab')
            ->call('open')
            ->assertSet('layout', 'compact')
            ->assertSee(__('FlowRise Assistant'))
            ->assertSeeHtml('flowrise-assistant__panel--compact')
            ->call('toggleExpand')
            ->assertSet('layout', 'expanded')
            ->assertSeeHtml('flowrise-assistant__panel--expanded')
            ->call('toggleExpand')
            ->assertSet('layout', 'compact')
            ->call('close')
            ->assertSet('layout', 'closed');
    }

    public function test_start_new_chat_clears_messages_and_conversation(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        Livewire::test(AssistantChatWidget::class)
            ->set('messages', [['role' => 'user', 'content' => 'Hello']])
            ->set('conversationId', 'conv-123')
            ->set('message', 'pending')
            ->call('startNewChat')
            ->assertSet('messages', [])
            ->assertSet('conversationId', null)
            ->assertSet('message', '');
    }

    public function test_compact_send_dispatches_turn_request_for_streaming(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        $this->mock(AssistantOrchestratorService::class, function ($mock): void {
            $mock->shouldNotReceive('prompt');
        });

        Livewire::test(AssistantChatWidget::class)
            ->call('open')
            ->set('message', 'What can you do?')
            ->call('send')
            ->assertSet('isThinking', true)
            ->assertSee('What can you do?')
            ->assertDispatched('assistant-turn-request', message: 'What can you do?', conversationId: null);
    }

    public function test_expanded_send_dispatches_turn_request_instead_of_blocking_prompt(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        $this->mock(AssistantOrchestratorService::class, function ($mock): void {
            $mock->shouldNotReceive('prompt');
        });

        Livewire::test(AssistantChatWidget::class)
            ->call('open')
            ->call('toggleExpand')
            ->set('message', 'Stream this')
            ->call('send')
            ->assertSet('isThinking', true)
            ->assertDispatched('assistant-turn-request', message: 'Stream this', conversationId: null);
    }

    public function test_mark_stream_error_shows_provider_message_once(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        Livewire::test(AssistantChatWidget::class)
            ->call('open')
            ->call('markStreamError', 'The Gemini model is temporarily overloaded. Please try again in a few minutes.')
            ->assertSet('isThinking', false)
            ->assertSee('The Gemini model is temporarily overloaded. Please try again in a few minutes.')
            ->assertDontSee('Streaming was interrupted');
    }

    public function test_confirmation_proposal_can_be_approved_or_cancelled(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        $proposal = [
            'action' => 'propose_schedule_appointment',
            'requires_confirmation' => true,
            'preview' => ['patient_id' => 1],
        ];

        $this->mock(AssistantProposalExecutor::class, function ($mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(json_encode([
                    'success' => true,
                    'message' => 'Appointment scheduled.',
                ], JSON_THROW_ON_ERROR));
        });

        Livewire::test(AssistantChatWidget::class)
            ->call('open')
            ->set('messages', [[
                'role' => 'assistant',
                'content' => 'Please review and confirm this action.',
                'proposal' => $proposal,
            ]])
            ->call('approveProposal', 0, $proposal)
            ->assertSet('messages.0.proposal', null)
            ->assertSee('Appointment scheduled.');

        Livewire::test(AssistantChatWidget::class)
            ->call('open')
            ->set('messages', [[
                'role' => 'assistant',
                'content' => 'Please review and confirm this action.',
                'proposal' => $proposal,
            ]])
            ->call('cancelProposal', 0)
            ->assertSet('messages.0.proposal', null)
            ->assertSee(__('Action cancelled.'));
    }

    public function test_widget_persists_session_and_loads_history(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        // Put active conversation and layout in session
        session([
            'ai_assistant_layout' => 'expanded',
            'ai_assistant_conversation_id' => 'conv-test-999',
        ]);

        // Insert dummy message to the db message table
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');
        DB::table($messagesTable)->insert([
            'id' => 'msg-test-1',
            'conversation_id' => 'conv-test-999',
            'user_id' => $user->id,
            'agent' => 'flow_rise_assistant',
            'role' => 'user',
            'content' => 'Hello AI',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(AssistantChatWidget::class)
            ->assertSet('layout', 'expanded')
            ->assertSet('conversationId', 'conv-test-999')
            ->assertSet('messages.0.role', 'user')
            ->assertSet('messages.0.content', 'Hello AI')
            // Toggle to closed and assert it's updated in session on render
            ->call('close')
            ->assertSet('layout', 'closed');

        $this->assertEquals('closed', session('ai_assistant_layout'));
    }

    protected function assistantUser(bool $grantPermission = true): User
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('assistant_widget_user', 'web');

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

<?php

namespace Modules\AI\Tests\Feature;

use App\Models\User;
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

    public function test_compact_send_appends_assistant_response_from_orchestrator(): void
    {
        $user = $this->assistantUser();
        $this->actingAs($user);

        $this->mock(AssistantOrchestratorService::class, function ($mock): void {
            $mock->shouldReceive('prompt')
                ->once()
                ->andReturn([
                    'text' => 'I can help with documentation.',
                    'conversation_id' => 'conv-abc',
                ]);
        });

        Livewire::test(AssistantChatWidget::class)
            ->call('open')
            ->set('message', 'What can you do?')
            ->call('send')
            ->assertSet('conversationId', 'conv-abc')
            ->assertSet('isThinking', false)
            ->assertSee('What can you do?')
            ->assertSee('I can help with documentation.');
    }

    public function test_expanded_send_dispatches_stream_request_instead_of_blocking_prompt(): void
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
            ->assertDispatched('assistant-stream-request', message: 'Stream this', conversationId: null);
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

<?php

namespace Modules\AI\Livewire;

use Illuminate\Contracts\View\View;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Livewire\Component;
use Modules\AI\Classes\Services\AssistantOrchestratorService;
use Modules\AI\Classes\Services\AssistantProposalExecutor;
use Modules\AI\Classes\Services\AssistantQuickPromptService;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Contracts\AssistantContract;
use Modules\Core\Models\CoreUser;

class AssistantChatWidget extends Component
{
    public string $layout = 'closed';

    public string $message = '';

    /** @var list<array{role: string, content: string, proposal?: array<string, mixed>|null}> */
    public array $messages = [];

    public ?string $conversationId = null;

    public bool $isThinking = false;

    public function mount(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        // Restore layout state and active conversation ID from session to persist sessions across page loads
        $this->layout = (string) session('ai_assistant_layout', 'closed');
        $this->conversationId = session('ai_assistant_conversation_id');

        if ($this->conversationId) {
            $this->loadMessagesFromHistory();
        }
    }

    public function open(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->layout = 'compact';
    }

    public function close(): void
    {
        $this->layout = 'closed';
    }

    public function toggleExpand(): void
    {
        if ($this->layout === 'expanded') {
            $this->layout = 'compact';

            return;
        }

        if ($this->layout === 'compact') {
            $this->layout = 'expanded';
        }
    }

    public function startNewChat(): void
    {
        $this->messages = [];
        $this->conversationId = null;
        $this->message = '';
        $this->isThinking = false;
    }

    public function sendQuickPrompt(string $text): void
    {
        $this->message = $text;
        $this->send();
    }

    public function send(): void
    {
        if (! $this->isAvailable() || $this->isThinking) {
            return;
        }

        $text = trim($this->message);
        if ($text === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->message = '';
        $this->isThinking = true;
        $this->dispatch('assistant-turn-request', message: $text, conversationId: $this->conversationId);
    }

    public function sendBlocking(string $text): void
    {
        $user = $this->user();
        if ($user === null) {
            return;
        }

        $this->isThinking = true;

        try {
            $result = app(AssistantOrchestratorService::class)->prompt(
                user: $user,
                message: $text,
                conversationId: $this->conversationId,
            );

            $this->conversationId = $result['conversation_id'] ?? $this->conversationId;
            $this->appendAssistantMessage($result['text']);
        } catch (\Throwable $exception) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $this->assistantUnavailableMessage($exception),
            ];
            report($exception);
        } finally {
            $this->isThinking = false;
        }
    }

    protected function assistantUnavailableMessage(\Throwable $exception): string
    {
        if ($exception instanceof RateLimitedException) {
            return __('The Gemini API quota or rate limit was exceeded. Check billing and usage in Google AI Studio, then try again.');
        }

        if ($exception instanceof ProviderOverloadedException) {
            return __('The Gemini model is temporarily overloaded. Please try again in a few minutes.');
        }

        if (str_contains(strtolower($exception->getMessage()), 'no ai providers were configured')) {
            return __('No AI provider is configured. Set GEMINI_API_KEY in .env and run php artisan config:clear.');
        }

        return __('The assistant is temporarily unavailable. Please try again.');
    }

    public function appendStreamedAssistantMessage(string $content, ?string $conversationId = null): void
    {
        if ($conversationId !== null) {
            $this->conversationId = $conversationId;
        }

        $this->appendAssistantMessage($content);
        $this->isThinking = false;
    }

    public function markStreamError(string $partial = ''): void
    {
        $message = trim($partial);

        if ($message === '') {
            $message = __('The assistant could not finish this reply. Please try again.');
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $message,
        ];
        $this->isThinking = false;
    }

    public function beginThinking(): void
    {
        $this->isThinking = true;
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    public function approveProposal(int $messageIndex, array $proposal): void
    {
        $user = $this->user();
        if ($user === null || $this->isThinking) {
            return;
        }

        $this->isThinking = true;

        try {
            $result = app(AssistantProposalExecutor::class)->execute($user, $proposal);
            $payload = json_decode($result, true);
            $summary = is_array($payload) && ($payload['message'] ?? null)
                ? (string) $payload['message']
                : __('Action completed.');

            $this->messages[$messageIndex]['proposal'] = null;
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $summary,
            ];
        } catch (\Throwable $exception) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => __('Could not complete that action. Please try again.'),
            ];
            report($exception);
        } finally {
            $this->isThinking = false;
        }
    }

    public function cancelProposal(int $messageIndex): void
    {
        if (! isset($this->messages[$messageIndex])) {
            return;
        }

        $this->messages[$messageIndex]['proposal'] = null;
        $this->messages[] = [
            'role' => 'assistant',
            'content' => __('Action cancelled.'),
        ];
    }

    /**
     * @return list<array{label: string, message: string}>
     */
    public function quickPrompts(): array
    {
        $user = $this->user();

        return $user ? app(AssistantQuickPromptService::class)->forUser($user) : [];
    }

    public function isAvailable(): bool
    {
        return app(AssistantContract::class)->isEnabled()
            && Feature::assistantEnabled()
            && $this->user()?->can(AssistantPermission::UseAssistant) === true;
    }

    public function render(): View|string
    {
        if (! $this->isAvailable()) {
            return '<div></div>';
        }

        // Sync active UI layout and conversation ID to session right before rendering
        session(['ai_assistant_layout' => $this->layout]);
        session(['ai_assistant_conversation_id' => $this->conversationId]);

        return view('ai::livewire.assistant-chat-widget');
    }

    protected function loadMessagesFromHistory(): void
    {
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        $messages = \Illuminate\Support\Facades\DB::table($messagesTable)
            ->where('conversation_id', $this->conversationId)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->messages = $messages->map(function ($msg) {
            if ($msg->role === 'assistant') {
                $proposal = $this->extractProposal($msg->content);
                return [
                    'role' => 'assistant',
                    'content' => $proposal['message'] ?? $msg->content,
                    'proposal' => $proposal['data'] ?? null,
                ];
            }

            return [
                'role' => $msg->role,
                'content' => $msg->content,
                'proposal' => null,
            ];
        })->toArray();
    }

    protected function appendAssistantMessage(string $response): void
    {
        $proposal = $this->extractProposal($response);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $proposal['message'] ?? $response,
            'proposal' => $proposal['data'] ?? null,
        ];
    }

    /**
     * @return array{message?: string, data?: array<string, mixed>|null}
     */
    protected function extractProposal(string $response): array
    {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && ($decoded['data']['requires_confirmation'] ?? false)) {
            return [
                'message' => (string) ($decoded['message'] ?? __('Please review and confirm this action.')),
                'data' => $decoded['data'],
            ];
        }

        return ['data' => null];
    }

    protected function user(): ?CoreUser
    {
        $user = auth()->user();

        return $user instanceof CoreUser ? $user : null;
    }
}

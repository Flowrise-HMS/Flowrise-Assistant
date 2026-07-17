<?php

namespace Modules\AI\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Streaming\Events\TextDelta;
use Modules\AI\Classes\Services\AssistantAuditService;
use Modules\AI\Classes\Services\AssistantOrchestratorService;
use Modules\AI\Classes\Support\AssistantBroadcast;
use Modules\Core\Models\CoreUser;
use Throwable;

class StreamAssistantTurnJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [2, 5, 10];

    public function __construct(
        public int|string $userId,
        public string $message,
        public string $turnId,
        public ?string $conversationId = null,
    ) {}

    public function handle(
        AssistantOrchestratorService $orchestrator,
        AssistantAuditService $auditService,
    ): void {
        $user = $this->resolveUser();
        if ($user === null) {
            return;
        }

        $startedAt = microtime(true);
        $conversationId = $this->conversationId;
        $fullText = '';

        try {
            $stream = $orchestrator->streamForBroadcast($user, $this->message, $conversationId);

            $buffer = '';
            $stream
                ->each(function ($event) use (&$fullText, &$conversationId, $user, &$buffer): void {
                    if (! $event instanceof TextDelta) {
                        return;
                    }

                    $fullText .= $event->delta;
                    $buffer .= $event->delta;

                    // Flush in chunks of ~12 chars to reduce WebSocket traffic and frontend DOM rendering overhead
                    if (strlen($buffer) >= 12) {
                        $this->flushChunk($user->getAuthIdentifier(), $conversationId, $buffer);
                        $buffer = '';
                    }
                })
                ->then(function ($response) use (&$conversationId, &$fullText): void {
                    if (filled($response->conversationId ?? null)) {
                        $conversationId = (string) $response->conversationId;
                    }

                    if ($fullText === '' && filled($response->text ?? null)) {
                        $fullText = (string) $response->text;
                    }
                });

            // Flush any remaining text in the buffer
            if ($buffer !== '') {
                $this->flushChunk($user->getAuthIdentifier(), $conversationId, $buffer);
            }

            // Prefer conversation id resolved on the streamable response after iteration.
            if (filled($stream->conversationId)) {
                $conversationId = (string) $stream->conversationId;
            }

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $auditService->log(
                user: $user,
                promptRedacted: $orchestrator->lastRedactedPrompt() ?? Str::limit($this->message, 500),
                responseSummary: Str::limit($fullText, 500),
                conversationId: $conversationId,
                latencyMs: $latencyMs,
            );

            AssistantBroadcast::sendNow('assistant.turn.completed', [
                'turn_id' => $this->turnId,
                'conversation_id' => $conversationId,
                'text' => $fullText,
            ], $user->getAuthIdentifier(), $conversationId);
        } catch (Throwable $exception) {
            if ($this->shouldRetry($exception)) {
                throw $exception;
            }

            report($exception);

            AssistantBroadcast::sendNow('assistant.turn.failed', [
                'turn_id' => $this->turnId,
                'conversation_id' => $conversationId,
                'message' => $this->safeMessage($exception),
            ], $user->getAuthIdentifier(), $conversationId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        AssistantBroadcast::sendNow('assistant.turn.failed', [
            'turn_id' => $this->turnId,
            'conversation_id' => $this->conversationId,
            'message' => $this->safeMessage($exception ?? new \RuntimeException('Stream failed.')),
        ], $this->userId, $this->conversationId);
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        return ($exception instanceof ProviderOverloadedException
            || $exception instanceof RateLimitedException)
            && $this->attempts() < $this->tries;
    }

    protected function flushChunk(int|string $userId, ?string $conversationId, string $delta): void
    {
        if ($delta === '') {
            return;
        }

        AssistantBroadcast::sendNow('assistant.token_chunk', [
            'turn_id' => $this->turnId,
            'conversation_id' => $conversationId,
            'delta' => $delta,
        ], $userId, $conversationId);
    }

    protected function safeMessage(Throwable $exception): string
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

    /**
     * Resolve the host auth user model (CoreUser is abstract).
     */
    protected function resolveUser(): ?CoreUser
    {
        /** @var class-string<CoreUser> $model */
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! is_a($model, CoreUser::class, true)) {
            return null;
        }

        $user = $model::query()->find($this->userId);

        return $user instanceof CoreUser ? $user : null;
    }
}

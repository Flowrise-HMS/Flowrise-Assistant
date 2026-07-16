<?php

namespace Modules\AI\Classes\Services;

use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Modules\AI\Ai\Agents\FlowRiseAssistantAgent;
use Modules\Core\Models\CoreUser;

class AssistantOrchestratorService
{
    protected ?string $lastRedactedPrompt = null;

    public function __construct(
        protected PhiRedactionService $redactor,
        protected AssistantAuditService $auditService,
    ) {}

    public function agentFor(CoreUser $user): FlowRiseAssistantAgent
    {
        return new FlowRiseAssistantAgent($user);
    }

    public function lastRedactedPrompt(): ?string
    {
        return $this->lastRedactedPrompt;
    }

    /**
     * @return array{text: string, conversation_id: ?string}
     */
    public function prompt(CoreUser $user, string $message, ?string $conversationId = null): array
    {
        $startedAt = microtime(true);
        $redactedPrompt = $this->redactor->redact($message);
        $this->lastRedactedPrompt = $redactedPrompt;

        $agent = $this->prepareAgent($user, $conversationId);

        $response = $agent->prompt($redactedPrompt);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $newConversationId = $response->conversationId ?? $conversationId;

        $this->auditService->log(
            user: $user,
            promptRedacted: $redactedPrompt,
            responseSummary: Str::limit((string) $response, 500),
            conversationId: $newConversationId,
            latencyMs: $latencyMs,
        );

        return [
            'text' => (string) $response,
            'conversation_id' => $newConversationId,
        ];
    }

    /**
     * @deprecated Prefer streamForBroadcast for Reverb turns
     */
    public function stream(CoreUser $user, string $message, ?string $conversationId = null)
    {
        return $this->streamForBroadcast($user, $message, $conversationId)
            ->usingVercelDataProtocol();
    }

    public function streamForBroadcast(CoreUser $user, string $message, ?string $conversationId = null): StreamableAgentResponse
    {
        $redactedPrompt = $this->redactor->redact($message);
        $this->lastRedactedPrompt = $redactedPrompt;

        return $this->prepareAgent($user, $conversationId)->stream($redactedPrompt);
    }

    protected function prepareAgent(CoreUser $user, ?string $conversationId): FlowRiseAssistantAgent
    {
        $agent = $this->agentFor($user);

        if ($conversationId) {
            return $agent->continue($conversationId, as: $user);
        }

        return $agent->forUser($user);
    }
}

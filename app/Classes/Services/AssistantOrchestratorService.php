<?php

namespace Modules\AI\Classes\Services;

use Illuminate\Support\Str;
use Modules\AI\Ai\Agents\FlowRiseAssistantAgent;
use Modules\Core\Models\CoreUser;

class AssistantOrchestratorService
{
    public function __construct(
        protected PhiRedactionService $redactor,
        protected AssistantAuditService $auditService,
    ) {}

    public function agentFor(CoreUser $user): FlowRiseAssistantAgent
    {
        return new FlowRiseAssistantAgent($user);
    }

    /**
     * @return array{text: string, conversation_id: ?string}
     */
    public function prompt(CoreUser $user, string $message, ?string $conversationId = null): array
    {
        $startedAt = microtime(true);
        $redactedPrompt = $this->redactor->redact($message);

        $agent = $this->agentFor($user);

        if ($conversationId) {
            $agent = $agent->continue($conversationId, as: $user);
        } else {
            $agent = $agent->forUser($user);
        }

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

    public function stream(CoreUser $user, string $message, ?string $conversationId = null)
    {
        $redactedPrompt = $this->redactor->redact($message);
        $agent = $this->agentFor($user);

        if ($conversationId) {
            $agent = $agent->continue($conversationId, as: $user);
        } else {
            $agent = $agent->forUser($user);
        }

        return $agent
            ->stream($redactedPrompt)
            ->usingVercelDataProtocol();
    }
}

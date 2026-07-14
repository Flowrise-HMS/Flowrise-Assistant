<?php

namespace Modules\AI\Classes\Services;

use Modules\AI\Models\AssistantAuditLog;
use Modules\Core\Models\CoreUser;

class AssistantAuditService
{
    /**
     * @param  list<array<string, mixed>>|null  $toolsInvoked
     * @param  list<array<string, mixed>>|null  $recordsAffected
     */
    public function log(
        CoreUser $user,
        ?string $promptRedacted,
        ?string $responseSummary,
        ?array $toolsInvoked = null,
        ?array $recordsAffected = null,
        ?string $conversationId = null,
        ?string $provider = null,
        ?string $model = null,
        ?int $latencyMs = null,
    ): AssistantAuditLog {
        if (! config('ai-assistant.audit.enabled', true)) {
            return new AssistantAuditLog;
        }

        $context = app(AssistantContextResolver::class)->resolve($user);

        return AssistantAuditLog::query()->create([
            'user_id' => $user->id,
            'branch_id' => $context['branch_id'],
            'conversation_id' => $conversationId,
            'prompt_redacted' => $promptRedacted,
            'response_summary' => $responseSummary,
            'tools_invoked' => $toolsInvoked,
            'records_affected' => $recordsAffected,
            'provider' => $provider,
            'model' => $model,
            'latency_ms' => $latencyMs,
        ]);
    }
}

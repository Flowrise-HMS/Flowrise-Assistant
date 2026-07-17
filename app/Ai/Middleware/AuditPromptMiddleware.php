<?php

namespace Modules\AI\Ai\Middleware;

use Closure;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Modules\AI\Classes\Services\AssistantAuditService;
use Modules\AI\Classes\Services\PhiRedactionService;
use Modules\Core\Models\CoreUser;

class AuditPromptMiddleware
{
    public function __construct(
        protected PhiRedactionService $redactor,
        protected AssistantAuditService $auditService,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $startedAt = microtime(true);

        $response = $next($prompt);

        return $response->then(function ($agentResponse) use ($prompt, $startedAt) {
            $user = auth()->user();

            if (! $user instanceof CoreUser
                && property_exists($prompt->agent, 'user')
                && $prompt->agent->user instanceof CoreUser) {
                $user = $prompt->agent->user;
            }

            if ($user === null) {
                return;
            }

            $this->auditService->log(
                user: $user,
                promptRedacted: $this->redactor->redact($prompt->prompt),
                responseSummary: Str::limit((string) $agentResponse->text, 500),
                latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            );
        });
    }
}

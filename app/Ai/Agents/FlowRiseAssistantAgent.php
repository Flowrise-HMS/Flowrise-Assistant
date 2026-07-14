<?php

namespace Modules\AI\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Modules\AI\Ai\Middleware\AuditPromptMiddleware;
use Modules\AI\Ai\Middleware\EnforcePermissionsMiddleware;
use Modules\AI\Ai\Middleware\RedactPhiMiddleware;
use Modules\AI\Classes\Services\ModuleCapabilityRegistry;
use Modules\AI\Classes\Services\ToolRegistry;
use Modules\AI\Classes\Services\UserCapabilityProfile;
use Modules\Core\Models\CoreUser;
use Stringable;

class FlowRiseAssistantAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(
        public CoreUser $user,
    ) {}

    public function instructions(): Stringable|string
    {
        $profile = UserCapabilityProfile::forUser($this->user);
        $modules = app(ModuleCapabilityRegistry::class);

        return <<<INSTRUCTIONS
You are FlowRise Assistant, a hospital operations copilot for FlowRise HMS.

{$profile->toPromptContext()}

{$modules->toPromptContext()}

Rules:
- Never invent patient data — use tools.
- For write operations: propose first, wait for explicit user confirmation, then execute.
- Clinical suggestions are advisory only — the clinician decides.
- Respect branch context and user permissions.
- If a module or permission is unavailable, explain clearly and suggest alternatives.
INSTRUCTIONS;
    }

    /**
     * @return list<Tool|SchedulingAgent|ClinicalDocumentationAgent|BillingAgent|HelpDeskAgent>
     */
    public function tools(): iterable
    {
        return app(ToolRegistry::class)->forUser($this->user);
    }

    public function middleware(): array
    {
        return [
            new EnforcePermissionsMiddleware,
            new RedactPhiMiddleware(app(\Modules\AI\Classes\Services\PhiRedactionService::class)),
            new AuditPromptMiddleware(
                app(\Modules\AI\Classes\Services\PhiRedactionService::class),
                app(\Modules\AI\Classes\Services\AssistantAuditService::class),
            ),
        ];
    }
}

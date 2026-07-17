<?php

namespace Modules\AI\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Modules\AI\Classes\Services\UserCapabilityProfile;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\Core\Models\CoreUser;

class EnforcePermissionsMiddleware
{
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $user = $this->resolveUser($prompt);

        if ($user === null) {
            abort(403, 'Authentication required for AI assistant.');
        }

        if (! $user->can(AssistantPermission::UseAssistant)) {
            abort(403, 'You do not have permission to use the AI assistant.');
        }

        $profile = UserCapabilityProfile::forUser($user);
        $prompt = $prompt->prepend($profile->toPromptContext());

        return $next($prompt);
    }

    protected function resolveUser(AgentPrompt $prompt): ?CoreUser
    {
        $user = auth()->user();

        if ($user instanceof CoreUser) {
            return $user;
        }

        $agent = $prompt->agent;

        if (property_exists($agent, 'user') && $agent->user instanceof CoreUser) {
            return $agent->user;
        }

        return null;
    }
}

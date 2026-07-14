<?php

namespace Modules\AI\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Modules\AI\Ai\Tools\System\DocumentationSearchTool;
use Modules\AI\Ai\Tools\System\GetSystemCapabilitiesTool;
use Modules\AI\Ai\Tools\System\OpenFilamentPageTool;
use Modules\Core\Models\CoreUser;
use Stringable;

class HelpDeskAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(
        public CoreUser $user,
    ) {}

    public function name(): string
    {
        return 'help_desk_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Answer how-to questions using FlowRise documentation and navigation guidance.';
    }

    public function instructions(): Stringable|string
    {
        return 'You are the FlowRise documentation and onboarding specialist. Search docs before answering. Provide step-by-step guidance and page links when helpful. Do not access patient data.';
    }

    public function tools(): iterable
    {
        return [
            new DocumentationSearchTool($this->user),
            new GetSystemCapabilitiesTool($this->user),
            new OpenFilamentPageTool($this->user),
        ];
    }
}

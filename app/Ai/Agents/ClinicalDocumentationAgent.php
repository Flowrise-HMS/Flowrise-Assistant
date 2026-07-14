<?php

namespace Modules\AI\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Modules\AI\Ai\Tools\Clinical\ExecuteCreateClinicalNoteTool;
use Modules\AI\Ai\Tools\Clinical\ExecuteRecordVitalsTool;
use Modules\AI\Ai\Tools\Clinical\ProposeCreateClinicalNoteTool;
use Modules\AI\Ai\Tools\Clinical\ProposeRecordVitalsTool;
use Modules\AI\Ai\Tools\Clinical\SearchDiagnosisCodesTool;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Models\CoreUser;
use Stringable;

class ClinicalDocumentationAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(
        public CoreUser $user,
    ) {}

    public function name(): string
    {
        return 'clinical_documentation_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Draft clinical notes, suggest diagnosis codes, and help record vitals.';
    }

    public function instructions(): Stringable|string
    {
        return 'You are the FlowRise clinical documentation specialist. Create drafts only. Label diagnosis suggestions as AI suggestions requiring clinical verification. Never auto-sign notes.';
    }

    public function tools(): iterable
    {
        $tools = [
            new SearchDiagnosisCodesTool($this->user),
            new ProposeCreateClinicalNoteTool($this->user),
            new ProposeRecordVitalsTool($this->user),
        ];

        if (Feature::writeActionsEnabled()) {
            $tools[] = new ExecuteCreateClinicalNoteTool($this->user);
            $tools[] = new ExecuteRecordVitalsTool($this->user);
        }

        return $tools;
    }
}

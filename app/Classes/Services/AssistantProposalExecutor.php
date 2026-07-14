<?php

namespace Modules\AI\Classes\Services;

use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Appointments\ExecuteScheduleAppointmentTool;
use Modules\AI\Ai\Tools\Clinical\ExecuteCreateClinicalNoteTool;
use Modules\AI\Ai\Tools\Clinical\ExecuteRecordVitalsTool;
use Modules\Core\Models\CoreUser;

class AssistantProposalExecutor
{
    /**
     * @param  array<string, mixed>  $proposal
     */
    public function execute(CoreUser $user, array $proposal): string
    {
        $action = (string) ($proposal['action'] ?? '');
        $preview = (array) ($proposal['preview'] ?? []);
        $preview['confirmed'] = true;

        $tool = match ($action) {
            'propose_schedule_appointment' => new ExecuteScheduleAppointmentTool($user),
            'propose_create_clinical_note' => new ExecuteCreateClinicalNoteTool($user),
            'propose_record_vitals' => new ExecuteRecordVitalsTool($user),
            default => null,
        };

        if ($tool === null) {
            return json_encode([
                'success' => false,
                'message' => 'Unknown proposal action.',
            ], JSON_THROW_ON_ERROR);
        }

        $toolKey = match ($action) {
            'propose_schedule_appointment' => 'execute_schedule_appointment',
            'propose_create_clinical_note' => 'execute_create_clinical_note',
            'propose_record_vitals' => 'execute_record_vitals',
            default => '',
        };

        if (! app(ToolAuthorizationService::class)->userCanRun($user, $toolKey)) {
            return json_encode([
                'success' => false,
                'message' => 'You do not have permission to execute this action.',
            ], JSON_THROW_ON_ERROR);
        }

        return (string) $tool->handle(new Request($preview));
    }
}

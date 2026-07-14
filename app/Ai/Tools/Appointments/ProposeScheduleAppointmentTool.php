<?php

namespace Modules\AI\Ai\Tools\Appointments;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Support\Feature;
use Modules\Appointment\Enums\AppointmentStatus;
use Modules\Patient\Models\Patient;
use Stringable;

class ProposeScheduleAppointmentTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Propose an appointment booking. Returns a preview payload for user confirmation before scheduling.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('appointment')) {
            return $this->moduleUnavailable('appointment');
        }

        if (! app(\Modules\AI\Classes\Services\ToolAuthorizationService::class)->userCanRun($this->user, 'propose_schedule_appointment')) {
            return $this->denial('propose_schedule_appointment');
        }

        $patient = Patient::query()->find((string) ($request['patient_id'] ?? ''));
        if ($patient === null) {
            return json_encode(['success' => false, 'message' => 'Patient not found.'], JSON_THROW_ON_ERROR);
        }

        return $this->success([
            'action' => 'propose_schedule_appointment',
            'requires_confirmation' => true,
            'preview' => [
                'patient_id' => $patient->id,
                'patient_name' => trim("{$patient->first_name} {$patient->last_name}"),
                'branch_id' => $request['branch_id'] ?? $patient->branch_id,
                'start_at' => $request['start_at'] ?? null,
                'end_at' => $request['end_at'] ?? null,
                'practitioner_primary_id' => $request['practitioner_primary_id'] ?? null,
                'status' => AppointmentStatus::BOOKED->value,
            ],
            'next_step' => 'Ask the user to confirm, then call execute_schedule_appointment with confirmed=true.',
        ], 'Appointment proposal ready for confirmation.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'patient_id' => $schema->string()->required(),
            'branch_id' => $schema->string(),
            'start_at' => $schema->string()->required()->description('ISO 8601 datetime'),
            'end_at' => $schema->string()->required()->description('ISO 8601 datetime'),
            'practitioner_primary_id' => $schema->string(),
        ];
    }
}

<?php

namespace Modules\AI\Ai\Tools\Appointments;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\ToolAuthorizationService;
use Modules\AI\Classes\Support\Feature;
use Modules\Appointment\Classes\Services\AppointmentSchedulingService;
use Modules\Appointment\Enums\AppointmentStatus;
use Stringable;

class ExecuteScheduleAppointmentTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Execute a confirmed appointment booking. Only call after explicit user confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Feature::writeActionsEnabled()) {
            return json_encode(['success' => false, 'message' => 'AI write actions are disabled.'], JSON_THROW_ON_ERROR);
        }

        if (! $this->moduleSupports('appointment')) {
            return $this->moduleUnavailable('appointment');
        }

        if (! app(ToolAuthorizationService::class)->userCanRun($this->user, 'execute_schedule_appointment')) {
            return $this->denial('execute_schedule_appointment');
        }

        if (! ($request['confirmed'] ?? false)) {
            return json_encode([
                'success' => false,
                'message' => 'User confirmation required. Call propose_schedule_appointment first.',
            ], JSON_THROW_ON_ERROR);
        }

        $appointment = app(AppointmentSchedulingService::class)->schedule([
            'branch_id' => $request['branch_id'],
            'patient_id' => $request['patient_id'],
            'practitioner_primary_id' => $request['practitioner_primary_id'] ?? null,
            'start_at' => $request['start_at'],
            'end_at' => $request['end_at'],
            'status' => AppointmentStatus::BOOKED,
        ]);

        return $this->success([
            'appointment_id' => $appointment->id,
            'status' => $appointment->status->value,
            'start_at' => optional($appointment->start_at)->toIso8601String(),
            'end_at' => optional($appointment->end_at)->toIso8601String(),
        ], 'Appointment scheduled successfully.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'confirmed' => $schema->boolean()->required(),
            'patient_id' => $schema->string()->required(),
            'branch_id' => $schema->string()->required(),
            'start_at' => $schema->string()->required(),
            'end_at' => $schema->string()->required(),
            'practitioner_primary_id' => $schema->string(),
        ];
    }
}

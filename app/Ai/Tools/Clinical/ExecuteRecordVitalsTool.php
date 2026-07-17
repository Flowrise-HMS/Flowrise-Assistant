<?php

namespace Modules\AI\Ai\Tools\Clinical;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\ToolAuthorizationService;
use Modules\AI\Classes\Support\Feature;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Patient\Models\Patient;
use Stringable;

class ExecuteRecordVitalsTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Record vital signs after explicit user confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Feature::writeActionsEnabled()) {
            return json_encode(['success' => false, 'message' => 'AI write actions are disabled.'], JSON_THROW_ON_ERROR);
        }

        if (! $this->moduleSupports('clinical')) {
            return $this->moduleUnavailable('clinical');
        }

        if (! app(ToolAuthorizationService::class)->userCanRun($this->user, 'execute_record_vitals')) {
            return $this->denial('execute_record_vitals');
        }

        if (! ($request['confirmed'] ?? false)) {
            return json_encode(['success' => false, 'message' => 'User confirmation required.'], JSON_THROW_ON_ERROR);
        }

        $patient = Patient::query()->findOrFail((string) $request['patient_id']);
        $vitals = (array) ($request['vitals'] ?? []);

        $record = app(VitalSignService::class)->record(
            patient: $patient,
            vitalData: $vitals,
            encounterId: $request['encounter_id'] ?? null,
            recordedBy: $this->user->id,
        );

        return $this->success([
            'vital_sign_id' => $record->id,
            'recorded_at' => optional($record->recorded_at)->toIso8601String(),
        ], 'Vitals recorded successfully.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'confirmed' => $schema->boolean()->required(),
            'patient_id' => $schema->string()->required(),
            'encounter_id' => $schema->string(),
            'vitals' => $schema->object()->required(),
        ];
    }
}

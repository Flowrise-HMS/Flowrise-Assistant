<?php

namespace Modules\AI\Ai\Tools\Clinical;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\Patient\Models\Patient;
use Stringable;

class ProposeRecordVitalsTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Propose recording vital signs for user confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('clinical')) {
            return $this->moduleUnavailable('clinical');
        }

        if (! app(\Modules\AI\Classes\Services\ToolAuthorizationService::class)->userCanRun($this->user, 'propose_record_vitals')) {
            return $this->denial('propose_record_vitals');
        }

        $patient = Patient::query()->find((string) ($request['patient_id'] ?? ''));
        if ($patient === null) {
            return json_encode(['success' => false, 'message' => 'Patient not found.'], JSON_THROW_ON_ERROR);
        }

        return $this->success([
            'action' => 'propose_record_vitals',
            'requires_confirmation' => true,
            'preview' => [
                'patient_id' => $patient->id,
                'encounter_id' => $request['encounter_id'] ?? null,
                'vitals' => $request['vitals'] ?? [],
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'patient_id' => $schema->string()->required(),
            'encounter_id' => $schema->string(),
            'vitals' => $schema->object()->required(),
        ];
    }
}

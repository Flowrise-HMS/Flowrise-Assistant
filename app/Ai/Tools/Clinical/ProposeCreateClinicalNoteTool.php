<?php

namespace Modules\AI\Ai\Tools\Clinical;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\Clinical\Enums\NoteType;
use Modules\Patient\Models\Patient;
use Stringable;

class ProposeCreateClinicalNoteTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Propose a draft clinical note for user review and confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('clinical')) {
            return $this->moduleUnavailable('clinical');
        }

        if (! app(\Modules\AI\Classes\Services\ToolAuthorizationService::class)->userCanRun($this->user, 'propose_create_clinical_note')) {
            return $this->denial('propose_create_clinical_note');
        }

        $patient = Patient::query()->find((string) ($request['patient_id'] ?? ''));
        if ($patient === null) {
            return json_encode(['success' => false, 'message' => 'Patient not found.'], JSON_THROW_ON_ERROR);
        }

        return $this->success([
            'action' => 'propose_create_clinical_note',
            'requires_confirmation' => true,
            'preview' => [
                'patient_id' => $patient->id,
                'encounter_id' => $request['encounter_id'] ?? null,
                'note_type' => $request['note_type'] ?? NoteType::SOAP->value,
                'subject' => $request['subject'] ?? 'Consultation note',
                'content' => $request['content'] ?? [],
            ],
            'disclaimer' => 'Draft only — clinician must review before saving.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'patient_id' => $schema->string()->required(),
            'encounter_id' => $schema->string(),
            'note_type' => $schema->string(),
            'subject' => $schema->string(),
            'content' => $schema->object(),
        ];
    }
}

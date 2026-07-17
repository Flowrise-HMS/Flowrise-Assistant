<?php

namespace Modules\AI\Ai\Tools\Clinical;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\ToolAuthorizationService;
use Modules\AI\Classes\Support\Feature;
use Modules\Clinical\Classes\Services\ClinicalNoteService;
use Modules\Clinical\Enums\NoteType;
use Modules\Patient\Models\Patient;
use Stringable;

class ExecuteCreateClinicalNoteTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Create a draft clinical note after explicit user confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Feature::writeActionsEnabled()) {
            return json_encode(['success' => false, 'message' => 'AI write actions are disabled.'], JSON_THROW_ON_ERROR);
        }

        if (! $this->moduleSupports('clinical')) {
            return $this->moduleUnavailable('clinical');
        }

        if (! app(ToolAuthorizationService::class)->userCanRun($this->user, 'execute_create_clinical_note')) {
            return $this->denial('execute_create_clinical_note');
        }

        if (! ($request['confirmed'] ?? false)) {
            return json_encode(['success' => false, 'message' => 'User confirmation required.'], JSON_THROW_ON_ERROR);
        }

        $patient = Patient::query()->findOrFail((string) $request['patient_id']);
        $noteType = NoteType::tryFrom((string) ($request['note_type'] ?? NoteType::CONSULTATION->value)) ?? NoteType::CONSULTATION;

        $note = app(ClinicalNoteService::class)->create(
            patient: $patient,
            noteType: $noteType,
            subject: (string) ($request['subject'] ?? 'Consultation note'),
            content: (array) ($request['content'] ?? []),
            encounterId: $request['encounter_id'] ?? null,
            authorId: $this->user->id,
        );

        return $this->success([
            'clinical_note_id' => $note->id,
            'status' => $note->status->value,
        ], 'Draft clinical note created.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'confirmed' => $schema->boolean()->required(),
            'patient_id' => $schema->string()->required(),
            'encounter_id' => $schema->string(),
            'note_type' => $schema->string(),
            'subject' => $schema->string(),
            'content' => $schema->object()->required(),
        ];
    }
}

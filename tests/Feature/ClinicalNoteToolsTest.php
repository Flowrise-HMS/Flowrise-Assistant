<?php

namespace Modules\AI\Tests\Feature;

use App\Models\User;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Clinical\ExecuteCreateClinicalNoteTool;
use Modules\AI\Ai\Tools\Clinical\ProposeCreateClinicalNoteTool;
use Modules\AI\Tests\Unit\AITestCase;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Role;

class ClinicalNoteToolsTest extends AITestCase
{
    public function test_propose_tool_defaults_to_a_valid_note_type_when_none_supplied(): void
    {
        $user = $this->clinicalUser();
        $patient = Patient::factory()->create();

        $payload = json_decode(
            (string) (new ProposeCreateClinicalNoteTool($user))->handle(new Request([
                'patient_id' => (string) $patient->id,
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($payload['success']);
        $this->assertSame(NoteType::CONSULTATION->value, $payload['data']['preview']['note_type']);
        $this->assertNotNull(NoteType::tryFrom($payload['data']['preview']['note_type']));
    }

    public function test_execute_tool_creates_note_with_valid_default_note_type(): void
    {
        $user = $this->clinicalUser();
        $patient = Patient::factory()->create();

        $payload = json_decode(
            (string) (new ExecuteCreateClinicalNoteTool($user))->handle(new Request([
                'confirmed' => true,
                'patient_id' => (string) $patient->id,
                'subject' => 'Consultation note',
                'content' => ['summary' => 'Follow-up visit'],
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($payload['success']);

        $note = ClinicalNote::query()->findOrFail($payload['data']['clinical_note_id']);

        $this->assertSame(NoteType::CONSULTATION, $note->note_type);
    }

    protected function clinicalUser(): User
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('clinical_note_author_test', 'web');
        $this->grantAssistantPermissions($role, ['Create ClinicalNote']);

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        return $user;
    }
}

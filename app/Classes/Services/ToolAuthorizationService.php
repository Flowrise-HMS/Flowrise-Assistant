<?php

namespace Modules\AI\Classes\Services;

use Modules\AI\Classes\Support\AssistantPermission;
use Modules\Core\Models\CoreUser;

class ToolAuthorizationService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $toolPermissions = [
        'search_patients' => ['ViewAny Patient'],
        'get_system_capabilities' => [AssistantPermission::UseAssistant],
        'documentation_search' => [AssistantPermission::UseAssistant],
        'open_filament_page' => [AssistantPermission::UseAssistant],
        'propose_schedule_appointment' => ['Create Appointment'],
        'execute_schedule_appointment' => ['Create Appointment'],
        'propose_record_vitals' => ['Create ClinicalNote'],
        'execute_record_vitals' => ['Create ClinicalNote'],
        'propose_create_clinical_note' => ['Create ClinicalNote'],
        'execute_create_clinical_note' => ['Create ClinicalNote'],
        'search_diagnosis_codes' => ['ViewAny Encounter', 'Create ClinicalNote'],
        'search_services' => ['ViewAny Service'],
        'get_invoice_balance' => ['View Invoice'],
        'propose_record_payment' => ['Create Payment'],
    ];

    public function userCanRun(CoreUser $user, string $toolKey): bool
    {
        if (! $user->can(AssistantPermission::UseAssistant)) {
            return false;
        }

        $permissions = $this->toolPermissions[$toolKey] ?? [AssistantPermission::UseAssistant];

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function authorizeOrFail(CoreUser $user, string $toolKey): void
    {
        if (! $this->userCanRun($user, $toolKey)) {
            abort(403, "You do not have permission to use the {$toolKey} assistant tool.");
        }
    }

    public function denialMessage(string $toolKey): string
    {
        return match ($toolKey) {
            'execute_schedule_appointment', 'propose_schedule_appointment' => 'You do not have permission to schedule appointments.',
            'execute_create_clinical_note', 'propose_create_clinical_note' => 'You do not have permission to create clinical notes.',
            'execute_record_vitals', 'propose_record_vitals' => 'You do not have permission to record vitals.',
            'get_invoice_balance' => 'You do not have permission to view invoices.',
            default => 'You do not have permission to perform this action.',
        };
    }
}

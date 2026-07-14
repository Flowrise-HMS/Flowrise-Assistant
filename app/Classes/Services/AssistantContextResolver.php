<?php

namespace Modules\AI\Classes\Services;

use Illuminate\Support\Facades\Session;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Models\CoreUser;

class AssistantContextResolver
{
    /**
     * @return array{page: string, branch_id: ?string, patient_id: ?string, encounter_id: ?string, patient_ref: ?string, encounter_ref: ?string}
     */
    public function resolve(?CoreUser $user = null): array
    {
        $user ??= auth()->user();
        $branchId = Session::get('current_branch_id') ?? $user?->branch_id;

        if ($branchId === null && $user !== null) {
            $branchId = app(BranchService::class)->getDefaultBranchId();
        }

        $patientId = Session::get('assistant.patient_id');
        $encounterId = Session::get('assistant.encounter_id');
        $page = Session::get('assistant.page', request()->route()?->getName() ?? 'unknown');

        return [
            'page' => (string) $page,
            'branch_id' => $branchId ? (string) $branchId : null,
            'patient_id' => $patientId ? (string) $patientId : null,
            'encounter_id' => $encounterId ? (string) $encounterId : null,
            'patient_ref' => $patientId ? '[PATIENT_CTX]' : null,
            'encounter_ref' => $encounterId ? '[ENCOUNTER_CTX]' : null,
        ];
    }

    public function setPageContext(?string $page, ?string $patientId = null, ?string $encounterId = null): void
    {
        if ($page !== null) {
            Session::put('assistant.page', $page);
        }

        if ($patientId !== null) {
            Session::put('assistant.patient_id', $patientId);
        }

        if ($encounterId !== null) {
            Session::put('assistant.encounter_id', $encounterId);
        }
    }
}

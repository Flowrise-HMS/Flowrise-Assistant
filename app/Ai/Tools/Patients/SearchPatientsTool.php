<?php

namespace Modules\AI\Ai\Tools\Patients;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\PhiRedactionService;
use Modules\Patient\Classes\Services\PatientSearchService;
use Stringable;

class SearchPatientsTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Search patients by name, MRN, phone, or email. Returns redacted identifiers only.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('patient')) {
            return $this->moduleUnavailable('patient');
        }

        if (! app(\Modules\AI\Classes\Services\ToolAuthorizationService::class)->userCanRun($this->user, 'search_patients')) {
            return $this->denial('search_patients');
        }

        $term = (string) ($request['query'] ?? '');
        $limit = (int) ($request['limit'] ?? 10);

        $patients = app(PatientSearchService::class)->search($term, max(1, min($limit, 25)));
        $redactor = app(PhiRedactionService::class);

        $results = $patients->map(function ($patient) use ($redactor) {
            return [
                'patient_ref' => $redactor->patientToken((string) $patient->id),
                'patient_id' => $patient->id,
                'mrn' => $patient->mrn,
                'display_name' => trim("{$patient->first_name} {$patient->last_name}"),
                'branch_id' => $patient->branch_id,
            ];
        })->values()->all();

        return $this->success(['patients' => $results]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Search term: name, MRN, phone, or email'),
            'limit' => $schema->integer()->min(1)->max(25),
        ];
    }
}

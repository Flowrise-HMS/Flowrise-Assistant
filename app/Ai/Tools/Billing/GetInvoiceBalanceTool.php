<?php

namespace Modules\AI\Ai\Tools\Billing;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\Billing\Services\PatientBalanceQueryService;
use Stringable;

class GetInvoiceBalanceTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Get a patient open invoice balance and deposit balance (read-only).';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('billing')) {
            return $this->moduleUnavailable('billing');
        }

        if (! app(\Modules\AI\Classes\Services\ToolAuthorizationService::class)->userCanRun($this->user, 'get_invoice_balance')) {
            return $this->denial('get_invoice_balance');
        }

        $patientId = (string) ($request['patient_id'] ?? '');
        $service = app(PatientBalanceQueryService::class);

        return $this->success([
            'patient_id' => $patientId,
            'open_balance' => $service->openBalanceForPatient($patientId),
            'deposit_balance' => $service->depositBalanceForPatient($patientId),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'patient_id' => $schema->string()->required(),
        ];
    }
}

<?php

namespace Modules\AI\Ai\Tools\Billing;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Support\Feature;
use Modules\Billing\Models\Invoice;
use Stringable;

class ProposeRecordPaymentTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Propose recording a payment against an invoice. Requires user confirmation before execution.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Feature::writeActionsEnabled()) {
            return json_encode(['success' => false, 'message' => 'AI write actions are disabled.'], JSON_THROW_ON_ERROR);
        }

        if (! $this->moduleSupports('billing')) {
            return $this->moduleUnavailable('billing');
        }

        if (! app(\Modules\AI\Classes\Services\ToolAuthorizationService::class)->userCanRun($this->user, 'propose_record_payment')) {
            return $this->denial('propose_record_payment');
        }

        $invoice = Invoice::query()->find((string) ($request['invoice_id'] ?? ''));
        if ($invoice === null) {
            return json_encode(['success' => false, 'message' => 'Invoice not found.'], JSON_THROW_ON_ERROR);
        }

        return $this->success([
            'action' => 'propose_record_payment',
            'requires_confirmation' => true,
            'preview' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance_due' => $invoice->balanceDue(),
                'amount' => $request['amount'] ?? null,
                'payment_method' => $request['payment_method'] ?? null,
            ],
            'next_step' => 'Payment recording must be confirmed by the user in the billing desk.',
        ], 'Payment proposal prepared — confirm in billing desk.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->string()->required(),
            'amount' => $schema->string(),
            'payment_method' => $schema->string(),
        ];
    }
}

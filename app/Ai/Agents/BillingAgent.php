<?php

namespace Modules\AI\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Modules\AI\Ai\Tools\Billing\GetInvoiceBalanceTool;
use Modules\AI\Ai\Tools\Billing\ProposeRecordPaymentTool;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Models\CoreUser;
use Stringable;

class BillingAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(
        public CoreUser $user,
    ) {}

    public function name(): string
    {
        return 'billing_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Explain invoice balances and prepare payment context.';
    }

    public function instructions(): Stringable|string
    {
        return 'You are the FlowRise billing specialist. Provide read-only balance explanations unless write actions are explicitly confirmed.';
    }

    public function tools(): iterable
    {
        $tools = [
            new GetInvoiceBalanceTool($this->user),
        ];

        if (Feature::writeActionsEnabled()) {
            $tools[] = new ProposeRecordPaymentTool($this->user);
        }

        return $tools;
    }
}

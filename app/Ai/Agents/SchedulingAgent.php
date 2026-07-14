<?php

namespace Modules\AI\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Modules\AI\Ai\Tools\Appointments\ExecuteScheduleAppointmentTool;
use Modules\AI\Ai\Tools\Appointments\ProposeScheduleAppointmentTool;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Models\CoreUser;
use Stringable;

class SchedulingAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function __construct(
        public CoreUser $user,
    ) {}

    public function name(): string
    {
        return 'scheduling_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Help with booking, rescheduling, check-in, and cancelling appointments.';
    }

    public function instructions(): Stringable|string
    {
        return 'You are the FlowRise scheduling specialist. Always propose appointment changes first and require explicit confirmation before executing writes.';
    }

    public function tools(): iterable
    {
        $tools = [
            new ProposeScheduleAppointmentTool($this->user),
        ];

        if (Feature::writeActionsEnabled()) {
            $tools[] = new ExecuteScheduleAppointmentTool($this->user);
        }

        return $tools;
    }
}

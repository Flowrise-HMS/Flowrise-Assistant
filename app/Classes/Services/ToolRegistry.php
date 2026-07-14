<?php

namespace Modules\AI\Classes\Services;

use Laravel\Ai\Contracts\Tool;
use Modules\AI\Ai\Agents\BillingAgent;
use Modules\AI\Ai\Agents\ClinicalDocumentationAgent;
use Modules\AI\Ai\Agents\HelpDeskAgent;
use Modules\AI\Ai\Agents\SchedulingAgent;
use Modules\AI\Ai\Tools\Appointments\ExecuteScheduleAppointmentTool;
use Modules\AI\Ai\Tools\Appointments\ProposeScheduleAppointmentTool;
use Modules\AI\Ai\Tools\Billing\GetInvoiceBalanceTool;
use Modules\AI\Ai\Tools\Billing\ProposeRecordPaymentTool;
use Modules\AI\Ai\Tools\Clinical\ExecuteCreateClinicalNoteTool;
use Modules\AI\Ai\Tools\Clinical\ExecuteRecordVitalsTool;
use Modules\AI\Ai\Tools\Clinical\ProposeCreateClinicalNoteTool;
use Modules\AI\Ai\Tools\Clinical\ProposeRecordVitalsTool;
use Modules\AI\Ai\Tools\Clinical\SearchDiagnosisCodesTool;
use Modules\AI\Ai\Tools\Clinical\SearchServicesTool;
use Modules\AI\Ai\Tools\Patients\SearchPatientsTool;
use Modules\AI\Ai\Tools\System\DocumentationSearchTool;
use Modules\AI\Ai\Tools\System\GetSystemCapabilitiesTool;
use Modules\AI\Ai\Tools\System\OpenFilamentPageTool;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Models\CoreUser;

class ToolRegistry
{
    public function __construct(
        protected ModuleCapabilityRegistry $moduleRegistry,
        protected ToolAuthorizationService $authorization,
    ) {}

    /**
     * @return list<Tool|SchedulingAgent|ClinicalDocumentationAgent|BillingAgent|HelpDeskAgent>
     */
    public function forUser(CoreUser $user): array
    {
        $tools = [
            new GetSystemCapabilitiesTool($user),
            new OpenFilamentPageTool($user),
        ];

        if (Feature::helpDeskEnabled() && $this->authorization->userCanRun($user, 'documentation_search')) {
            $tools[] = new DocumentationSearchTool($user);
            $tools[] = new HelpDeskAgent($user);
        }

        if ($this->moduleRegistry->supports('patient') && $this->authorization->userCanRun($user, 'search_patients')) {
            $tools[] = new SearchPatientsTool($user);
        }

        if ($this->moduleRegistry->supports('appointment') && $this->authorization->userCanRun($user, 'propose_schedule_appointment')) {
            $tools[] = new SchedulingAgent($user);
            $tools[] = new ProposeScheduleAppointmentTool($user);

            if (Feature::writeActionsEnabled()) {
                $tools[] = new ExecuteScheduleAppointmentTool($user);
            }
        }

        if (Feature::clinicalCopilotEnabled() && $this->moduleRegistry->supports('clinical')) {
            if ($this->authorization->userCanRun($user, 'search_diagnosis_codes')) {
                $tools[] = new SearchDiagnosisCodesTool($user);
            }

            if ($this->authorization->userCanRun($user, 'search_services')) {
                $tools[] = new SearchServicesTool($user);
            }

            if ($this->authorization->userCanRun($user, 'propose_create_clinical_note')) {
                $tools[] = new ClinicalDocumentationAgent($user);
                $tools[] = new ProposeCreateClinicalNoteTool($user);
                $tools[] = new ProposeRecordVitalsTool($user);

                if (Feature::writeActionsEnabled()) {
                    $tools[] = new ExecuteCreateClinicalNoteTool($user);
                    $tools[] = new ExecuteRecordVitalsTool($user);
                }
            }
        }

        if ($this->moduleRegistry->supports('billing') && $this->authorization->userCanRun($user, 'get_invoice_balance')) {
            $tools[] = new BillingAgent($user);
            $tools[] = new GetInvoiceBalanceTool($user);

            if (Feature::writeActionsEnabled() && $this->authorization->userCanRun($user, 'propose_record_payment')) {
                $tools[] = new ProposeRecordPaymentTool($user);
            }
        }

        return $tools;
    }
}

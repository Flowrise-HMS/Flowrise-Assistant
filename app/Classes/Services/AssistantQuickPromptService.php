<?php

namespace Modules\AI\Classes\Services;

use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Models\CoreUser;

class AssistantQuickPromptService
{
    public function __construct(
        protected ModuleCapabilityRegistry $modules,
        protected ToolAuthorizationService $authorization,
        protected AssistantContextResolver $context,
    ) {}

    /**
     * @return list<array{label: string, message: string}>
     */
    public function forUser(CoreUser $user): array
    {
        if (! $user->can(AssistantPermission::UseAssistant)) {
            return [];
        }

        $prompts = [
            [
                'label' => __('What can you help with?'),
                'message' => 'What can you help me with on FlowRise?',
            ],
        ];

        if (Feature::helpDeskEnabled()) {
            $prompts[] = [
                'label' => __('How to enter vitals'),
                'message' => 'How do I enter vitals for a patient?',
            ];
        }

        $page = $this->context->resolve($user)['page'] ?? '';

        if ($this->modules->supports('appointment')
            && $this->authorization->userCanRun($user, 'propose_schedule_appointment')) {
            $prompts[] = [
                'label' => __('Schedule follow-up'),
                'message' => 'Help me schedule a follow-up appointment.',
            ];
        }

        if (Feature::clinicalCopilotEnabled()
            && $this->modules->supports('clinical')
            && $this->authorization->userCanRun($user, 'propose_create_clinical_note')) {
            $prompts[] = [
                'label' => __('Draft clinical note'),
                'message' => 'Help me draft a consultation clinical note.',
            ];
        }

        if (str_contains((string) $page, 'clinical') || str_contains((string) $page, 'workspace')) {
            $prompts[] = [
                'label' => __('Clinical workspace help'),
                'message' => 'What can you help me with on the clinical workspace?',
            ];
        }

        if ($this->modules->supports('billing')
            && $this->authorization->userCanRun($user, 'get_invoice_balance')) {
            $prompts[] = [
                'label' => __('Patient balance'),
                'message' => 'How do I check a patient invoice balance?',
            ];
        }

        return array_slice($prompts, 0, 6);
    }
}

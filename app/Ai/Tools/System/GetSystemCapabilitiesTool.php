<?php

namespace Modules\AI\Ai\Tools\System;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\ModuleCapabilityRegistry;
use Modules\AI\Classes\Services\UserCapabilityProfile;
use Modules\AI\Classes\Support\Feature;
use Stringable;

class GetSystemCapabilitiesTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Return what the assistant can help with for this user on this installation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->authorizeTool('get_system_capabilities');

        $profile = UserCapabilityProfile::forUser($this->user);
        $modules = app(ModuleCapabilityRegistry::class);

        return $this->success([
            'user' => [
                'name' => $profile->name,
                'roles' => $profile->roles,
                'permissions' => $profile->permissions,
            ],
            'modules' => $modules->all(),
            'available_modules' => $modules->availableModules(),
            'features' => [
                'help_desk' => Feature::helpDeskEnabled(),
                'clinical_copilot' => Feature::clinicalCopilotEnabled(),
                'write_actions' => Feature::writeActionsEnabled(),
            ],
            'suggested_prompts' => $this->suggestedPrompts(),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function suggestedPrompts(): array
    {
        $prompts = ['What can you help me with on FlowRise?'];

        if (Feature::helpDeskEnabled()) {
            $prompts[] = 'How do I enter vitals for a patient?';
        }

        if (app(ModuleCapabilityRegistry::class)->supports('appointment')) {
            $prompts[] = 'How do I schedule a follow-up appointment?';
        }

        if (app(ModuleCapabilityRegistry::class)->supports('clinical')) {
            $prompts[] = 'How do I create a clinical encounter?';
        }

        return $prompts;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

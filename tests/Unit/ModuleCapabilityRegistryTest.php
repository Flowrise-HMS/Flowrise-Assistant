<?php

namespace Modules\AI\Tests\Unit;

use Modules\AI\Classes\Services\ModuleCapabilityRegistry;
use Modules\Core\Settings\FeatureSettings;

class ModuleCapabilityRegistryTest extends AITestCase
{
    public function test_disabled_appointment_feature_is_not_supported(): void
    {
        $settings = app(FeatureSettings::class);
        $settings->appointments_enabled = false;
        $settings->save();

        $registry = app(ModuleCapabilityRegistry::class);

        $this->assertFalse($registry->supports('appointment'));
        $this->assertNotContains('appointment', $registry->availableModules());
    }

    public function test_prompt_context_mentions_unavailable_modules(): void
    {
        $settings = app(FeatureSettings::class);
        $settings->appointments_enabled = false;
        $settings->save();

        $context = app(ModuleCapabilityRegistry::class)->toPromptContext();

        $this->assertStringContainsString('NOT available', $context);
    }
}

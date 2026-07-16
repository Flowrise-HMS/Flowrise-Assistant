<?php

namespace Modules\AI\Tests\Feature;

use Modules\AI\Providers\HooksServiceProvider;
use Modules\AI\Tests\Unit\AITestCase;

class AssistantViteAssetsTest extends AITestCase
{
    public function test_module_vite_config_exports_assistant_widget_css_path(): void
    {
        $configPath = module_path('AI', 'vite.config.js');
        $this->assertFileExists($configPath);

        $contents = file_get_contents($configPath);
        $this->assertNotFalse($contents);
        $this->assertStringContainsString('export const paths', $contents);
        $this->assertStringContainsString(HooksServiceProvider::AssistantWidgetCss, $contents);
    }

    public function test_assistant_widget_css_source_lives_in_module_assets(): void
    {
        $this->assertFileExists(module_path('AI', 'resources/assets/css/assistant-widget.css'));
        $this->assertFileDoesNotExist(module_path('AI', 'resources/css/assistant-widget.css'));
        $this->assertFileDoesNotExist(public_path('css/app/assistant-widget.css'));
        $this->assertFileDoesNotExist(public_path('css/flowrise-hms/ai/assistant-widget.css'));
    }
}

<?php

namespace Modules\AI\Classes\Support;

use Modules\Core\Settings\FeatureSettings;

class Feature
{
    public static function assistantEnabled(): bool
    {
        return app(FeatureSettings::class)->ai_assistant_enabled;
    }

    public static function helpDeskEnabled(): bool
    {
        return self::assistantEnabled()
            && app(FeatureSettings::class)->ai_help_desk_enabled;
    }

    public static function clinicalCopilotEnabled(): bool
    {
        return self::assistantEnabled()
            && app(FeatureSettings::class)->ai_clinical_copilot_enabled;
    }

    public static function writeActionsEnabled(): bool
    {
        return self::assistantEnabled()
            && app(FeatureSettings::class)->ai_write_actions_enabled;
    }
}

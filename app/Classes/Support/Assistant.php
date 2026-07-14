<?php

namespace Modules\AI\Classes\Support;

use Modules\Core\Contracts\AssistantContract;
use Modules\Core\Settings\FeatureSettings;

class Assistant implements AssistantContract
{
    public function isEnabled(): bool
    {
        return Feature::assistantEnabled();
    }
}

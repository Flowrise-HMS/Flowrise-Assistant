<?php

namespace Modules\AI\Classes\Support;

use Modules\Core\Contracts\AssistantContract;

class Assistant implements AssistantContract
{
    public function isEnabled(): bool
    {
        return Feature::assistantEnabled();
    }
}

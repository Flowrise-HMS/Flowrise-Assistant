<?php

namespace Modules\AI\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class AIPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'AI';
    }

    public function getId(): string
    {
        return 'ai';
    }

    public function boot(Panel $panel): void {}
}

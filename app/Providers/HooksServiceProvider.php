<?php

namespace Modules\AI\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\Feature;
use Modules\AI\Livewire\AssistantChatWidget;

class HooksServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('assistant-widget', __DIR__.'/../../resources/css/assistant-widget.css'),
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function (): string {
                if (! Feature::assistantEnabled()) {
                    return '';
                }

                $user = auth()->user();
                if ($user === null || ! $user->can(AssistantPermission::UseAssistant)) {
                    return '';
                }

                return Livewire::mount(AssistantChatWidget::class);
            }
        );
    }
}

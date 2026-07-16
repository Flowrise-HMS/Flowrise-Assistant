<?php

namespace Modules\AI\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\ViteException;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\Feature;
use Modules\AI\Livewire\AssistantChatWidget;

class HooksServiceProvider extends ServiceProvider
{
    public const AssistantWidgetCss = 'Modules/AI/resources/assets/css/assistant-widget.css';

    public function register(): void {}

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            function (): string {
                if (! $this->assistantUiEnabled()) {
                    return '';
                }

                try {
                    return Vite::withEntryPoints([self::AssistantWidgetCss])->toHtml();
                } catch (ViteException) {
                    // Manifest missing during tests / before first `pnpm run build` — avoid crashing the panel.
                    return '';
                }
            }
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function (): string {
                if (! $this->assistantUiEnabled()) {
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

    protected function assistantUiEnabled(): bool
    {
        return Feature::assistantEnabled();
    }
}

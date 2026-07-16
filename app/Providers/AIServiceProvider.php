<?php

namespace Modules\AI\Providers;

use Modules\AI\Classes\Support\Assistant;
use Modules\AI\Classes\Support\FilamentEchoRegistrar;
use Modules\AI\Console\IndexDocumentationCommand;
use Modules\Core\Contracts\AssistantContract;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AIServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AI';

    protected string $nameLower = 'ai';

    /**
     * @var list<class-string>
     */
    protected array $commands = [
        IndexDocumentationCommand::class,
    ];

    /**
     * @var list<class-string>
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
        HooksServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(AssistantContract::class, Assistant::class);
    }

    public function boot(): void
    {
        parent::boot();

        require module_path($this->name, 'routes/channels.php');

        $this->app->booted(static fn (): mixed => FilamentEchoRegistrar::register());
        $this->registerModulePermissions();
    }

    protected function registerModulePermissions(): void
    {
        $this->app->booted(function (): void {
            $permissions = config('ai.permissions', []);
            if ($permissions === []) {
                return;
            }

            $existing = config('filament-shield.custom_permissions', []);
            config(['filament-shield.custom_permissions' => array_merge($existing, $permissions)]);
        });
    }
}

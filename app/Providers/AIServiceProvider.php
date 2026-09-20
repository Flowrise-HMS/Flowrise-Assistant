<?php

namespace Modules\AI\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\AI\Classes\Support\Assistant;
use Modules\AI\Classes\Support\FilamentEchoRegistrar;
use Modules\AI\Console\IndexDocumentationCommand;
use Modules\AI\Console\PruneAssistantAuditCommand;
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
        PruneAssistantAuditCommand::class,
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

        // nwidart merges every config file under "ai.<file>"; the services read
        // these keys as "ai-assistant.*", so merge the file under that key too.
        $this->mergeConfigFrom(module_path($this->name, 'config/ai-assistant.php'), 'ai-assistant');

        $this->app->bind(AssistantContract::class, Assistant::class);
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('ai:prune-assistant-audit')->daily();
    }

    public function boot(): void
    {
        parent::boot();

        require module_path($this->name, 'routes/channels.php');

        // Custom permissions are merged into Shield by CoreServiceProvider for
        // every enabled module, so no module-specific merge is needed here.
        $this->app->booted(static fn (): mixed => FilamentEchoRegistrar::register());
    }
}

<?php

namespace Modules\AI\Ai\Tools\Concerns;

use Modules\AI\Classes\Services\ModuleCapabilityRegistry;
use Modules\AI\Classes\Services\ToolAuthorizationService;
use Modules\Core\Models\CoreUser;

trait InteractsWithAssistantUser
{
    public function __construct(
        protected CoreUser $user,
    ) {}

    protected function authorizeTool(string $toolKey): void
    {
        app(ToolAuthorizationService::class)->authorizeOrFail($this->user, $toolKey);
    }

    protected function moduleSupports(string $module): bool
    {
        return app(ModuleCapabilityRegistry::class)->supports($module);
    }

    protected function denial(string $toolKey): string
    {
        return json_encode([
            'success' => false,
            'message' => app(ToolAuthorizationService::class)->denialMessage($toolKey),
        ], JSON_THROW_ON_ERROR);
    }

    protected function moduleUnavailable(string $module): string
    {
        return json_encode([
            'success' => false,
            'message' => ucfirst($module).' is not available on this installation.',
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function success(array $data, ?string $message = null): string
    {
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], JSON_THROW_ON_ERROR);
    }
}

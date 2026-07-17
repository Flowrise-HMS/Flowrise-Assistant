<?php

namespace Modules\AI\Ai\Tools\System;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Route;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Stringable;

class OpenFilamentPageTool implements Tool
{
    use InteractsWithAssistantUser;

    /**
     * @var array<string, string>
     */
    protected array $pageRoutes = [
        'clinical_workspace' => 'filament.admin.clinical.pages.clinical-workspace',
        'billing_desk' => 'filament.admin.billing.pages.billing-desk',
        'appointments' => 'filament.admin.appointment.resources.appointments.index',
        'patients' => 'filament.admin.patient.resources.patients.index',
        'feature_settings' => 'filament.admin.core.pages.settings.manage-feature-settings',
    ];

    public function description(): Stringable|string
    {
        return 'Provide a navigation link to a known Filament page in FlowRise.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->authorizeTool('open_filament_page');

        $pageKey = (string) ($request['page_key'] ?? '');
        $routeName = $this->pageRoutes[$pageKey] ?? null;

        if ($routeName === null || ! Route::has($routeName)) {
            return json_encode([
                'success' => false,
                'message' => 'Unknown or unavailable page key.',
                'available_pages' => array_keys($this->pageRoutes),
            ], JSON_THROW_ON_ERROR);
        }

        return $this->success([
            'page_key' => $pageKey,
            'url' => route($routeName),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page_key' => $schema->string()->required()->description('Page key such as clinical_workspace, billing_desk, appointments, patients'),
        ];
    }
}

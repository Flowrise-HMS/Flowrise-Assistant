<?php

namespace Modules\AI\Classes\Services;

use Modules\Core\Settings\FeatureSettings;
use Modules\Core\Support\ModuleAvailability;

class ModuleCapabilityRegistry
{
    /**
     * @return array<string, array{label: string, installed: bool, enabled: bool, feature_on: bool, capabilities: list<string>}>
     */
    public function all(): array
    {
        $settings = app(FeatureSettings::class);

        return [
            'patient' => $this->module('Patient', 'Patient', true, ['search', 'register']),
            'appointment' => $this->module('Appointment', 'Appointments', $settings->appointments_enabled, [
                'schedule', 'reschedule', 'check_in', 'cancel', 'waitlist',
            ]),
            'clinical' => $this->module('Clinical', 'Clinical', $settings->clinical_workspace_enabled, [
                'encounters', 'notes', 'vitals', 'diagnoses', 'orders',
            ]),
            'billing' => $this->module('Billing', 'Billing', $settings->billing_desk_enabled, [
                'invoices', 'balances', 'payments',
            ]),
            'pharmacy' => $this->module('Pharmacy', 'Pharmacy', $settings->pharmacy_pos_enabled, ['pos', 'dispensing']),
            'diagnostics' => $this->module('Diagnostics', 'Diagnostics', $settings->diagnostics_enabled, ['lab', 'imaging']),
            'insurance' => $this->module('Insurance', 'Insurance', $settings->insurance_enabled, ['claims', 'coverage']),
            'inventory' => $this->module('Inventory', 'Inventory', $settings->inventory_pharmacy_procurement, [
                'stock', 'requisitions', 'transfers',
            ]),
        ];
    }

    public function supports(string $module): bool
    {
        $module = strtolower($module);
        $capabilities = $this->all()[$module] ?? null;

        if ($capabilities === null) {
            return false;
        }

        return $capabilities['installed']
            && $capabilities['enabled']
            && $capabilities['feature_on'];
    }

    /**
     * @return list<string>
     */
    public function availableModules(): array
    {
        return collect($this->all())
            ->filter(fn (array $module): bool => $module['installed'] && $module['enabled'] && $module['feature_on'])
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function unavailableModules(): array
    {
        return collect($this->all())
            ->reject(fn (array $module): bool => $module['installed'] && $module['enabled'] && $module['feature_on'])
            ->map(fn (array $module, string $key): string => $module['label'].($module['installed'] ? ' (feature disabled)' : ' (not installed)'))
            ->values()
            ->all();
    }

    public function toPromptContext(): string
    {
        $available = $this->availableModules();
        $unavailable = $this->unavailableModules();

        $lines = [
            'Available on this installation: '.($available !== [] ? implode(', ', $available) : 'none').'.',
        ];

        if ($unavailable !== []) {
            $lines[] = 'NOT available: '.implode(', ', $unavailable).'.';
            $lines[] = 'Do not offer help for unavailable modules. Explain clearly when asked.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{label: string, installed: bool, enabled: bool, feature_on: bool, capabilities: list<string>}
     */
    protected function module(string $name, string $label, bool $featureOn, array $capabilities): array
    {
        $installed = ModuleAvailability::installed($name);
        $enabled = ModuleAvailability::enabled($name);

        return [
            'label' => $label,
            'installed' => $installed,
            'enabled' => $enabled,
            'feature_on' => $featureOn,
            'capabilities' => ($installed && $enabled && $featureOn) ? $capabilities : [],
        ];
    }
}

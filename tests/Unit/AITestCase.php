<?php

namespace Modules\AI\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\Core\Settings\FeatureSettings;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class AITestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireModule('AI');
        $this->migrateModules();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedFeatureSettings();
        $this->enableAssistantFeatures();
    }

    protected function seedFeatureSettings(): void
    {
        $defaults = [
            'insurance_enabled' => true,
            'pharmacy_pos_enabled' => true,
            'pharmacy_reports_enabled' => true,
            'clinical_workspace_enabled' => true,
            'mar_board_enabled' => true,
            'appointments_enabled' => true,
            'diagnostics_enabled' => true,
            'billing_desk_enabled' => true,
            'patient_quick_add_enabled' => true,
            'patient_import_enabled' => true,
            'patient_hospital_card_enabled' => true,
            'staff_id_card_enabled' => true,
            'inventory_pharmacy_procurement' => true,
            'inventory_ward_requisitions' => true,
            'inventory_inter_branch_transfers' => true,
            'ai_assistant_enabled' => false,
            'ai_clinical_copilot_enabled' => true,
            'ai_write_actions_enabled' => false,
            'ai_help_desk_enabled' => true,
        ];

        foreach ($defaults as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'features', 'name' => $name],
                [
                    'payload' => json_encode($value, JSON_THROW_ON_ERROR),
                    'locked' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        app()->forgetInstance(FeatureSettings::class);
        $this->artisan('settings:clear-cache');
    }

    protected function enableAssistantFeatures(): void
    {
        $settings = app(FeatureSettings::class);
        $settings->ai_assistant_enabled = true;
        $settings->ai_help_desk_enabled = true;
        $settings->ai_clinical_copilot_enabled = true;
        $settings->ai_write_actions_enabled = true;
        $settings->save();
    }

    protected function grantAssistantPermissions(Role $role, array $extra = []): void
    {
        $permissions = array_merge([AssistantPermission::UseAssistant], $extra);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $role->givePermissionTo($permission);
        }
    }
}

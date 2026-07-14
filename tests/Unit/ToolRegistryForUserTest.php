<?php

namespace Modules\AI\Tests\Unit;

use App\Models\User;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Services\ToolRegistry;
use Modules\AI\Classes\Services\UserCapabilityProfile;
use Modules\Core\Models\Branch;
use Spatie\Permission\Models\Role;

class ToolRegistryForUserTest extends AITestCase
{
    public function test_super_admin_gets_broader_tool_set_than_receptionist(): void
    {
        $branch = Branch::factory()->create();

        $superAdminRole = Role::findOrCreate('super_admin_test', 'web');
        $receptionistRole = Role::findOrCreate('receptionist_test', 'web');

        $this->grantAssistantPermissions($superAdminRole, [
            'ViewAny Patient',
            'Create Appointment',
            'Create ClinicalNote',
            'View Invoice',
            'ViewAny Encounter',
            'ViewAny Service',
            'Create Payment',
        ]);

        $this->grantAssistantPermissions($receptionistRole, [
            'ViewAny Patient',
            'Create Appointment',
            'View Invoice',
        ]);

        $superAdmin = User::factory()->create(['branch_id' => $branch->id]);
        $superAdmin->assignRole($superAdminRole);

        $receptionist = User::factory()->create(['branch_id' => $branch->id]);
        $receptionist->assignRole($receptionistRole);

        $superTools = collect(app(ToolRegistry::class)->forUser($superAdmin))->map(fn ($tool) => $tool::class)->all();
        $receptionTools = collect(app(ToolRegistry::class)->forUser($receptionist))->map(fn ($tool) => $tool::class)->all();

        $this->assertGreaterThan(count($receptionTools), count($superTools));
        $this->assertContains(\Modules\AI\Ai\Tools\Clinical\SearchDiagnosisCodesTool::class, $superTools);
        $this->assertNotContains(\Modules\AI\Ai\Tools\Clinical\SearchDiagnosisCodesTool::class, $receptionTools);
    }

    public function test_user_capability_profile_includes_roles_and_permissions(): void
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('doctor_test', 'web');
        $this->grantAssistantPermissions($role, ['ViewAny Patient']);

        $user = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Dr. Test']);
        $user->assignRole($role);

        $profile = UserCapabilityProfile::forUser($user);

        $this->assertSame('Dr. Test', $profile->name);
        $this->assertContains('doctor_test', $profile->roles);
        $this->assertContains(AssistantPermission::UseAssistant, $profile->permissions);
        $this->assertStringContainsString('Dr. Test', $profile->toPromptContext());
    }
}

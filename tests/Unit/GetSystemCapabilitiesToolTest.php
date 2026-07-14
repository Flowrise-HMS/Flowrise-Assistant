<?php

namespace Modules\AI\Tests\Unit;

use App\Models\User;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\System\DocumentationSearchTool;
use Modules\AI\Ai\Tools\System\GetSystemCapabilitiesTool;
use Modules\AI\Models\DocumentationChunk;
use Modules\Core\Models\Branch;
use Spatie\Permission\Models\Role;

class GetSystemCapabilitiesToolTest extends AITestCase
{
    public function test_returns_effective_capabilities_for_user(): void
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('assistant_user', 'web');
        $this->grantAssistantPermissions($role, ['ViewAny Patient']);

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        $payload = json_decode((string) (new GetSystemCapabilitiesTool($user))->handle(new Request([])), true);

        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('modules', $payload['data']);
        $this->assertArrayHasKey('available_modules', $payload['data']);
        $this->assertTrue($payload['data']['features']['help_desk']);
    }

    public function test_documentation_search_returns_indexed_chunks(): void
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('helpdesk_user', 'web');
        $this->grantAssistantPermissions($role);

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        DocumentationChunk::query()->create([
            'path' => 'docs/user-guide/clinical-workflows.md',
            'title' => 'Clinical workflows',
            'audience' => 'staff',
            'module' => 'clinical',
            'chunk_index' => 0,
            'content' => 'How to enter vitals for a patient in the clinical workspace.',
            'content_hash' => hash('sha256', 'vitals'),
        ]);

        $payload = json_decode((string) (new DocumentationSearchTool($user))->handle(new Request([
            'query' => 'enter vitals',
        ])), true);

        $this->assertTrue($payload['success']);
        $this->assertNotEmpty($payload['data']['results']);
        $this->assertStringContainsString('vitals', strtolower($payload['data']['results'][0]['excerpt']));
    }
}

<?php

namespace Modules\AI\Tests\Unit;

use App\Models\User;
use Modules\AI\Ai\Agents\FlowRiseAssistantAgent;
use Modules\Core\Models\Branch;
use Spatie\Permission\Models\Role;

class FlowRiseAssistantAgentTest extends AITestCase
{
    public function test_agent_can_be_faked_for_prompting(): void
    {
        $branch = Branch::factory()->create();
        $role = Role::findOrCreate('assistant_agent_user', 'web');
        $this->grantAssistantPermissions($role, ['ViewAny Patient']);

        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        $this->actingAs($user);

        FlowRiseAssistantAgent::fake([
            'I can help with documentation and permitted workflows.',
        ]);

        $response = (new FlowRiseAssistantAgent($user))
            ->forUser($user)
            ->prompt('What can you help with?');

        $this->assertStringContainsString('documentation', (string) $response);
        FlowRiseAssistantAgent::assertPrompted('What can you help with?');
    }
}

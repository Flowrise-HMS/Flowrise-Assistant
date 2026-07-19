<?php

namespace Modules\AI\Tests\Browser;

use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Modules\Core\Models\Branch;

it('renders the floating AI widget and opens on click', function () {
    $this->migrateModules();
    $this->seed(ShieldSeeder::class);

    $branch = Branch::factory()->create();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);
    $admin->assignRole('super_admin');

    $this->actingAs($admin);

    $page = visit(route('filament.corepanel.pages.dashboard'));

    $page->assertNoJavaScriptErrors()
        ->assertSee('Dashboard')
        // Verify the floating AI assistant FAB is rendered
        ->assertSeeAnythingIn('.flowrise-assistant__fab')
        // Click the FAB to open the widget
        ->click('.flowrise-assistant__fab')
        // Verify the chat panel displays
        ->assertSee(__('Ask FlowRise Assistant…'));
});

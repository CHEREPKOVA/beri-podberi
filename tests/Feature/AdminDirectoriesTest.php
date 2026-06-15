<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDirectoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_open_directories_hub_and_company_types(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->get(route('admin.directories.index'))
            ->assertOk()
            ->assertSee('Типы компаний')
            ->assertSee('Статусы претензий');

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->get(route('admin.company-types.index'))
            ->assertOk()
            ->assertSee('Производитель');
    }

    public function test_platform_order_status_labels_use_dictionary(): void
    {
        $this->assertSame('Новый', \App\Models\PlatformOrder::statusLabels()['new']);
    }
}

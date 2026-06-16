<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_manager_can_access_companies_but_not_staff(): void
    {
        $managerRole = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();
        $manager = User::factory()->create();
        $manager->roles()->sync([$managerRole->id]);

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $managerRole->id])
            ->get(route('admin.companies.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $managerRole->id])
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    public function test_analyst_is_denied_access_to_admin_management_modules(): void
    {
        $analystRole = Role::query()->where('slug', Role::SLUG_ANALYST)->firstOrFail();
        $analyst = User::factory()->create();
        $analyst->roles()->sync([$analystRole->id]);

        $this->actingAs($analyst)
            ->withSession(['current_role_id' => $analystRole->id])
            ->get(route('admin.companies.index'))
            ->assertForbidden();

        $this->actingAs($analyst)
            ->withSession(['current_role_id' => $analystRole->id])
            ->get(route('admin.catalog.index'))
            ->assertForbidden();
    }

    public function test_permission_override_is_applied_on_next_request_without_relogin(): void
    {
        $managerRole = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();
        $companiesPermission = Permission::query()->where('slug', 'companies.manage')->firstOrFail();
        $manager = User::factory()->create();
        $manager->roles()->sync([$managerRole->id]);

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $managerRole->id])
            ->get(route('admin.companies.index'))
            ->assertOk();

        $manager->userPermissions()->sync([
            $companiesPermission->id => ['is_allowed' => false],
        ]);

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $managerRole->id])
            ->get(route('admin.companies.index'))
            ->assertForbidden();
    }

    public function test_admin_audit_logs_staff_suspend_action(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $managerRole = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();

        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $staff = User::factory()->create([
            'is_active' => true,
        ]);
        $staff->roles()->sync([$managerRole->id]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->post(route('admin.staff.suspend', $staff))
            ->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => $admin->id,
            'action' => 'admin.staff.suspend',
            'required_permission' => 'staff.manage',
        ]);

        $log = DB::table('admin_action_logs')
            ->where('admin_id', $admin->id)
            ->where('action', 'admin.staff.suspend')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $context = json_decode((string) $log->context, true);
        $this->assertSame('POST', $context['method'] ?? null);
        $this->assertSame(302, $context['response_status'] ?? null);
    }
}

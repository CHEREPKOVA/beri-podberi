<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminJournalService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_manager_with_audit_permission_can_view_journal(): void
    {
        $managerRole = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();
        $manager = User::factory()->create();
        $manager->roles()->sync([$managerRole->id]);

        $this->actingAs($manager)
            ->withSession(['current_role_id' => $managerRole->id])
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Журнал');
    }

    public function test_analyst_without_audit_permission_is_denied_journal(): void
    {
        $analystRole = Role::query()->where('slug', Role::SLUG_ANALYST)->firstOrFail();
        $analyst = User::factory()->create();
        $analyst->roles()->sync([$analystRole->id]);

        $this->actingAs($analyst)
            ->withSession(['current_role_id' => $analystRole->id])
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }

    public function test_audit_log_stores_admin_name_and_required_permission(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $managerRole = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();

        $admin = User::factory()->create(['name' => 'Главный Админ']);
        $admin->roles()->sync([$adminRole->id]);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->roles()->sync([$managerRole->id]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->post(route('admin.staff.suspend', $staff))
            ->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_id' => $admin->id,
            'admin_name' => 'Главный Админ',
            'action' => 'admin.staff.suspend',
            'required_permission' => 'staff.manage',
        ]);
    }

    public function test_audit_log_survives_admin_user_deletion(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $managerRole = Role::query()->where('slug', Role::SLUG_MANAGER)->firstOrFail();

        $admin = User::factory()->create(['name' => 'Временный Админ']);
        $admin->roles()->sync([$adminRole->id]);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->roles()->sync([$managerRole->id]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->post(route('admin.staff.suspend', $staff));

        $logId = (int) DB::table('admin_action_logs')->value('id');
        $admin->delete();

        $this->assertDatabaseHas('admin_action_logs', [
            'id' => $logId,
            'admin_id' => null,
            'admin_name' => 'Временный Админ',
            'action' => 'admin.staff.suspend',
        ]);
    }

    public function test_audit_index_can_filter_by_company_name(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        DB::table('admin_action_logs')->insert([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'admin.companies.update',
            'required_permission' => 'companies.manage',
            'company_name' => 'ООО Ромашка',
            'company_type' => Role::SLUG_MANUFACTURER,
            'context' => json_encode(['response_status' => 302], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_action_logs')->insert([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'admin.staff.suspend',
            'required_permission' => 'staff.manage',
            'context' => json_encode(['response_status' => 302], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->get(route('admin.audit.index', ['company_name' => 'Ромашка']))
            ->assertOk()
            ->assertSee('Обновление данных компании')
            ->assertDontSee('Блокировка сотрудника');
    }

    public function test_audit_show_displays_log_details(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $admin = User::factory()->create(['name' => 'Аудитор']);
        $admin->roles()->sync([$adminRole->id]);

        $logId = DB::table('admin_action_logs')->insertGetId([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'admin.system-settings.update',
            'required_permission' => 'directories.manage',
            'context' => json_encode([
                'response_status' => 302,
                'method' => 'PUT',
                'path' => 'admin/system-settings',
                'input' => ['site_name' => 'Бери-Подбери'],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionName = Permission::query()->where('slug', 'directories.manage')->value('name');

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->get(route('admin.audit.show', ['source' => 'admin_action', 'id' => $logId]))
            ->assertOk()
            ->assertSee('Изменение системных настроек')
            ->assertSee('Аудитор')
            ->assertSee($permissionName)
            ->assertSee('site_name')
            ->assertSee('Бери-Подбери');
    }

    public function test_journal_collects_events_from_multiple_sources_into_single_array(): void
    {
        $adminRole = Role::query()->where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $admin = User::factory()->create(['name' => 'Админ']);
        $admin->roles()->sync([$adminRole->id]);

        DB::table('admin_action_logs')->insert([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'action' => 'admin.staff.suspend',
            'required_permission' => 'staff.manage',
            'context' => json_encode(['response_status' => 302], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $manufacturerId = DB::table('manufacturer_profiles')->insertGetId([
            'user_id' => $admin->id,
            'full_name' => 'ООО Производитель',
            'inn' => '7700000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $distributorId = DB::table('distributor_profiles')->insertGetId([
            'user_id' => $admin->id,
            'full_name' => 'ООО Дистрибьютор',
            'inn' => '7700000002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('manufacturer_distributor_partnership_logs')->insert([
            'manufacturer_profile_id' => $manufacturerId,
            'distributor_profile_id' => $distributorId,
            'action' => 'added',
            'description' => 'Добавлен партнёр',
            'performed_by_user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $events = app(AdminJournalService::class)->collect(Request::create('/'));

        $this->assertCount(2, $events);
        $this->assertSame('partnership', $events->first()['source']);
        $this->assertSame('admin_action', $events->last()['source']);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $adminRole->id])
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Блокировка сотрудника')
            ->assertSee('Дистрибьютор добавлен в партнёры');
    }
}

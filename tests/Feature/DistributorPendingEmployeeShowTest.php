<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\DistributorEmployeeRole;
use App\Models\DistributorWarehouse;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\CurrentRoleService;
use App\Services\DistributorEmployeeService;
use App\Support\CompanyStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DistributorPendingEmployeeShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_multi_role_admin_can_open_pending_employee_card(): void
    {
        Mail::fake();

        $distributorRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $endCompanyRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();

        $admin = User::factory()->create([
            'name' => 'Дистрибьютор и Покупатель',
            'email' => 'distributor-buyer@test.com',
        ]);
        $admin->roles()->attach([
            $distributorRole->id => [
                'company_name' => 'АккумТрейд Опт',
                'company_type' => 'distributor',
                'company_status' => CompanyStatus::ACTIVE,
            ],
            $endCompanyRole->id => [
                'company_name' => 'СТО-АвтоПлюс',
                'company_type' => 'end_company',
                'company_status' => CompanyStatus::ACTIVE,
            ],
        ]);

        $profile = $admin->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'АккумТрейд Опт', 'short_name' => 'АккумТрейд Опт']);
        $region = Region::factory()->create();
        DistributorWarehouse::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Склад Москва',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);
        app(DistributorEmployeeService::class)->ensureDefaultRoles($profile);

        $employeeRole = DistributorEmployeeRole::query()->where('name', 'Менеджер по продажам')->firstOrFail();

        app(CurrentRoleService::class)->set($admin, $distributorRole->id);

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $distributorRole->id])
            ->get(route('distributor.employees.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $distributorRole->id])
            ->post(route('distributor.employees.store'), [
                'name' => '111',
                'email' => '222@22.ru',
                'employee_role_id' => $employeeRole->id,
                'all_warehouses' => '1',
            ])
            ->assertRedirect(route('distributor.employees.index'));

        $employee = User::query()->where('email', '222@22.ru')->firstOrFail();
        $this->assertFalse($employee->is_active);

        // Сессию роли не подставляем заново — проверяем, что она не сбрасывается между запросами.
        $index = $this->actingAs($admin)
            ->withSession(['current_role_id' => $distributorRole->id])
            ->get(route('distributor.employees.index'));
        $index->assertOk();
        $this->assertEquals(
            $distributorRole->id,
            session('current_role_id'),
            'После списка сотрудников роль в сессии должна сохраниться'
        );

        $response = $this->actingAs($admin)
            ->get(route('distributor.employees.show', $employee));

        $response->assertOk();
        $response->assertSee('111');
        $response->assertDontSee('Выберите, в каком качестве');
        $this->assertFalse(
            $response->isRedirect() && str_contains((string) $response->headers->get('Location'), 'role-select'),
            'Не должно редиректить на выбор роли'
        );
        $this->assertEquals($distributorRole->id, session('current_role_id'));
    }

    public function test_opening_pending_employee_does_not_clear_role_when_admin_listed(): void
    {
        Mail::fake();

        $distributorRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $endCompanyRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();

        $admin = User::factory()->create(['email' => 'multi-dst@test.com']);
        $admin->roles()->attach([
            $distributorRole->id => [
                'company_name' => 'ТестДистр',
                'company_type' => 'distributor',
                'company_status' => CompanyStatus::ACTIVE,
            ],
            $endCompanyRole->id => [
                'company_name' => 'ТестСТО',
                'company_type' => 'end_company',
                'company_status' => CompanyStatus::ACTIVE,
            ],
        ]);
        $profile = $admin->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'ТестДистр', 'short_name' => 'ТД']);
        $region = Region::factory()->create();
        DistributorWarehouse::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Склад',
            'address' => 'Адрес',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);
        app(DistributorEmployeeService::class)->ensureDefaultRoles($profile);
        $employeeRole = DistributorEmployeeRole::query()->where('name', 'Менеджер по продажам')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['current_role_id' => $distributorRole->id])
            ->post(route('distributor.employees.store'), [
                'name' => 'Pending',
                'email' => 'pending-emp@test.com',
                'employee_role_id' => $employeeRole->id,
                'all_warehouses' => '1',
            ])
            ->assertRedirect();

        $employee = User::query()->where('email', 'pending-emp@test.com')->firstOrFail();

        // Список (там eager-load ролей админа) → карточка без повторной установки роли
        $this->actingAs($admin)
            ->withSession(['current_role_id' => $distributorRole->id])
            ->get(route('distributor.employees.index'))
            ->assertOk();

        $show = $this->get(route('distributor.employees.show', $employee));

        $show->assertOk()
            ->assertSee('Pending');
        $this->assertNotEquals(
            route('role.select'),
            $show->headers->get('Location')
        );
    }
}

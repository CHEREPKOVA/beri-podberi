<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\DistributorEmployeeRole;
use App\Models\DistributorWarehouse;
use App\Models\EndCompanyEmployeeRole;
use App\Models\ManufacturerEmployeeRole;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentRoleService;
use App\Services\DistributorEmployeeService;
use App\Services\EndCompanyEmployeeService;
use App\Services\ManufacturerEmployeeService;
use App\Support\CompanyStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeesCabinetIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_employees_cannot_open_other_cabinets_staff_sections(): void
    {
        $manufacturerEmployee = $this->inviteManufacturerEmployee();
        $distributorEmployee = $this->inviteDistributorEmployee();
        $endCompanyEmployee = $this->inviteEndCompanyEmployee();

        $this->actingAsRole($manufacturerEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('distributor.employees.index'))
            ->assertForbidden();
        $this->actingAsRole($manufacturerEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('end_company.employees.index'))
            ->assertForbidden();
        $this->actingAsRole($manufacturerEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('buyer.orders.index'))
            ->assertForbidden();

        $this->actingAsRole($distributorEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('manufacturer.employees.index'))
            ->assertForbidden();
        $this->actingAsRole($distributorEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('end_company.employees.index'))
            ->assertForbidden();
        $this->actingAsRole($distributorEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('buyer.cart.index'))
            ->assertForbidden();

        $this->actingAsRole($endCompanyEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('manufacturer.employees.index'))
            ->assertForbidden();
        $this->actingAsRole($endCompanyEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('distributor.employees.index'))
            ->assertForbidden();
        $this->actingAsRole($endCompanyEmployee, Role::SLUG_COMPANY_EMPLOYEE)
            ->get(route('buyer.orders.index'))
            ->assertOk();
    }

    private function inviteManufacturerEmployee(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        $admin = User::factory()->create(['email' => 'iso-mfr-admin@example.com']);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Изоляция Завод',
            'company_type' => 'manufacturer',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateManufacturerProfile();
        $profile->update(['full_name' => 'Изоляция Завод']);
        Warehouse::factory()->create(['manufacturer_profile_id' => $profile->id]);
        app(ManufacturerEmployeeService::class)->ensureDefaultRoles($profile);
        $employeeRole = ManufacturerEmployeeRole::query()->where('name', 'Оператор')->firstOrFail();

        return $this->acceptInvite('iso-mfr-emp@example.com', function () use ($admin, $employeeRole) {
            $this->actingAsRole($admin, Role::SLUG_MANUFACTURER)
                ->post(route('manufacturer.employees.store'), [
                    'name' => 'Сотрудник завода',
                    'email' => 'iso-mfr-emp@example.com',
                    'employee_role_id' => $employeeRole->id,
                    'permissions' => $employeeRole->normalizedPermissions(),
                    'all_warehouses' => '1',
                ]);
        });
    }

    private function inviteDistributorEmployee(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $admin = User::factory()->create(['email' => 'iso-dst-admin@example.com']);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Изоляция Дистр',
            'company_type' => 'distributor',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'Изоляция Дистр']);
        $region = Region::factory()->create();
        DistributorWarehouse::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Склад',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);
        app(DistributorEmployeeService::class)->ensureDefaultRoles($profile);
        $employeeRole = DistributorEmployeeRole::query()->where('name', 'Менеджер по продажам')->firstOrFail();

        return $this->acceptInvite('iso-dst-emp@example.com', function () use ($admin, $employeeRole) {
            $this->actingAsRole($admin, Role::SLUG_DISTRIBUTOR)
                ->post(route('distributor.employees.store'), [
                    'name' => 'Сотрудник дистра',
                    'email' => 'iso-dst-emp@example.com',
                    'employee_role_id' => $employeeRole->id,
                    'permissions' => $employeeRole->normalizedPermissions(),
                    'all_warehouses' => '1',
                ]);
        });
    }

    private function inviteEndCompanyEmployee(): User
    {
        $role = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $admin = User::factory()->create(['email' => 'iso-end-admin@example.com']);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Изоляция СТО',
            'company_type' => 'end_company',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateEndCompanyProfile();
        $profile->update(['full_name' => 'Изоляция СТО']);
        app(EndCompanyEmployeeService::class)->ensureDefaultRoles($profile);
        $employeeRole = EndCompanyEmployeeRole::query()->where('name', 'Менеджер по закупкам')->firstOrFail();

        return $this->acceptInvite('iso-end-emp@example.com', function () use ($admin, $employeeRole) {
            $this->actingAsRole($admin, Role::SLUG_END_COMPANY)
                ->post(route('end_company.employees.store'), [
                    'name' => 'Сотрудник СТО',
                    'email' => 'iso-end-emp@example.com',
                    'employee_role_id' => $employeeRole->id,
                    'permissions' => $employeeRole->normalizedPermissions(),
                    'all_addresses' => '1',
                ]);
        });
    }

    /**
     * @param  callable(): void  $invite
     */
    private function acceptInvite(string $email, callable $invite): User
    {
        Mail::fake();
        $invite();

        $plainToken = null;
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use ($email, &$plainToken) {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $plainToken = $mail->plainToken;

            return true;
        });

        auth()->logout();
        $this->post(route('employee-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);
        auth()->logout();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function actingAsRole(User $user, string $slug): self
    {
        $user->load('roles');
        $role = $user->roles->firstWhere('slug', $slug);
        app(CurrentRoleService::class)->set($user, $role->id);

        return $this->actingAs($user)->withSession(['current_role_id' => $role->id]);
    }
}

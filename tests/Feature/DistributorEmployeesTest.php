<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Mail\EmployeePasswordResetMail;
use App\Models\DistributorContact;
use App\Models\DistributorEmployeeRole;
use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
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

class DistributorEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_open_employees_section(): void
    {
        [$admin] = $this->makeDistributorAdmin();

        $this->actingAsDistributor($admin)
            ->get(route('distributor.employees.index'))
            ->assertOk()
            ->assertSee('Сотрудники компании')
            ->assertSee('Добавить сотрудника');
    }

    public function test_employee_without_staff_rights_cannot_see_employees_section(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('distributor.employees.index'))
            ->assertForbidden();
    }

    public function test_employee_with_personnel_rights_can_open_employees_section(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $hr = $this->makeActiveEmployee($admin, 'hr@example.com', 'Secret123', 'Администратор компании');

        $this->actingAsEmployee($hr)
            ->get(route('distributor.employees.index'))
            ->assertOk()
            ->assertSee('Сотрудники компании');
    }

    public function test_admin_invites_employee_and_invitation_activates_account(): void
    {
        Mail::fake();
        [$admin] = $this->makeDistributorAdmin();
        $role = DistributorEmployeeRole::query()->where('name', 'Менеджер по продажам')->firstOrFail();

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.store'), [
                'name' => 'Иван Петров',
                'email' => 'ivan.sales@example.com',
                'employee_role_id' => $role->id,
                'all_warehouses' => '1',
            ])
            ->assertRedirect(route('distributor.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'ivan.sales@example.com',
            'is_active' => false,
        ]);

        $plainToken = null;
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use (&$plainToken) {
            if (! $mail->hasTo('ivan.sales@example.com')) {
                return false;
            }
            $plainToken = $mail->plainToken;

            return true;
        });

        auth()->logout();
        $this->post(route('employee-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertRedirect(route('dashboard'));

        $employee = User::query()->where('email', 'ivan.sales@example.com')->firstOrFail();
        $this->assertTrue($employee->is_active);
        $this->assertTrue($employee->isDistributorStaff());
        $this->assertSame(
            CompanyStatus::ACTIVE,
            strtolower((string) $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE)?->pivot?->company_status)
        );
    }

    public function test_sales_employee_can_open_orders_but_not_purchases(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('distributor.orders.index'))
            ->assertOk();

        $this->actingAsEmployee($employee)
            ->get(route('distributor.purchases.index'))
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->get(route('distributor.employees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_reset_password_and_block_employee(): void
    {
        Mail::fake();
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.reset_password', $employee))
            ->assertRedirect();

        Mail::assertSent(EmployeePasswordResetMail::class);

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.block', $employee))
            ->assertRedirect();

        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertFalse(
            (bool) DistributorContact::query()->where('user_id', $employee->id)->value('is_active')
        );
        $this->assertStringContainsString(
            'Неактивный менеджер',
            DistributorContact::query()->where('user_id', $employee->id)->first()->displayName()
        );
    }

    public function test_employee_cannot_be_deleted_after_operational_history(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeActiveEmployee($admin);
        $profile = $admin->distributorProfile;
        $warehouse = $profile->warehouses()->first();
        $product = DistributorProduct::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Тестовый товар',
            'internal_sku' => 'SKU-EMP-1',
            'retail_price' => 100,
            'status' => DistributorProduct::STATUS_ACTIVE,
        ]);
        DistributorProductStock::query()->create([
            'distributor_product_id' => $product->id,
            'distributor_warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'stock_updated_by_user_id' => $employee->id,
        ]);

        $this->actingAsDistributor($admin)
            ->delete(route('distributor.employees.destroy', $employee))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $employee->id]);
    }

    public function test_employee_without_history_can_be_deleted(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeActiveEmployee($admin, 'delete-me@example.com');

        $this->actingAsDistributor($admin)
            ->delete(route('distributor.employees.destroy', $employee))
            ->assertRedirect(route('distributor.employees.index'));

        $this->assertDatabaseMissing('users', ['email' => 'delete-me@example.com']);
    }

    public function test_admin_can_create_custom_role(): void
    {
        [$admin] = $this->makeDistributorAdmin();

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.roles.store'), [
                'name' => 'Региональный директор',
                'permissions' => [
                    'orders' => 'manage',
                    'purchases' => 'view',
                    'products' => 'view',
                    'employees' => '1',
                ],
            ])
            ->assertRedirect(route('distributor.employees.index', ['tab' => 'roles']));

        $this->assertDatabaseHas('distributor_employee_roles', [
            'name' => 'Региональный директор',
        ]);
    }

    public function test_default_system_roles_match_tz(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $names = $admin->distributorProfile->employeeRoles()->orderBy('sort_order')->pluck('name')->all();

        $this->assertSame([
            'Администратор компании',
            'Менеджер по продажам',
            'Менеджер по закупкам',
            'Оператор каталога',
            'Логист / менеджер склада',
            'Поддержка клиентов',
        ], $names);

        $sales = DistributorEmployeeRole::query()->where('name', 'Менеджер по продажам')->firstOrFail();
        $this->assertSame('manage', $sales->normalizedPermissions()['orders']);
        $this->assertSame('none', $sales->normalizedPermissions()['purchases']);
        $this->assertFalse($sales->normalizedPermissions()['manufacturer_chat']);

        $buyer = DistributorEmployeeRole::query()->where('name', 'Менеджер по закупкам')->firstOrFail();
        $this->assertSame('manage', $buyer->normalizedPermissions()['purchases']);
        $this->assertTrue($buyer->normalizedPermissions()['manufacturer_chat']);
    }

    public function test_employee_sees_only_assigned_warehouses(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $profile = $admin->distributorProfile;
        $moscow = $profile->warehouses()->firstOrFail();
        $region = Region::factory()->create();
        $rostov = DistributorWarehouse::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Склад Ростов',
            'address' => 'Ростов',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_REGIONAL,
            'is_active' => true,
        ]);

        $employee = $this->makeActiveEmployee(
            $admin,
            'wh-staff@example.com',
            'Secret123',
            'Логист / менеджер склада',
            [
                'all_warehouses' => '0',
                'warehouse_ids' => [$moscow->id],
            ],
        );

        $this->actingAsEmployee($employee)
            ->get(route('distributor.warehouses.index'))
            ->assertOk()
            ->assertSee('Склад Москва')
            ->assertDontSee('Склад Ростов');

        $this->assertFalse(
            app(\App\Services\DistributorStaffAccess::class)->canAccessWarehouse($employee, (int) $rostov->id)
        );
    }

    public function test_purchase_manager_can_chat_with_manufacturer_sales_cannot(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $sales = $this->makeActiveEmployee($admin, 'sales-chat@example.com');
        $purchaser = $this->makeActiveEmployee($admin, 'buy-chat@example.com', 'Secret123', 'Менеджер по закупкам');

        $access = app(\App\Services\DistributorStaffAccess::class);
        $this->assertFalse($access->canAccessSection($sales, 'manufacturer_chat'));
        $this->assertTrue($access->canAccessSection($purchaser, 'manufacturer_chat'));
    }

    /**
     * @return array{0: User}
     */
    private function makeDistributorAdmin(): array
    {
        $role = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $admin = User::factory()->create([
            'name' => 'Админ Дистрибьютора',
            'email' => 'dst-admin@example.com',
        ]);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Дистр Сотрудники',
            'company_type' => 'distributor',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'Дистр Сотрудники', 'short_name' => 'ДС']);
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

        return [$admin->fresh(['roles', 'distributorProfile'])];
    }

    private function makeActiveEmployee(
        User $admin,
        string $email = 'dst-employee@example.com',
        string $password = 'Secret123',
        string $roleName = 'Менеджер по продажам',
        array $extra = [],
    ): User {
        Mail::fake();
        $role = DistributorEmployeeRole::query()->where('name', $roleName)->firstOrFail();

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.store'), array_merge([
                'name' => 'Сотрудник Дистрибьютора',
                'email' => $email,
                'employee_role_id' => $role->id,
                'all_warehouses' => '1',
            ], $extra));

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
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        auth()->logout();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function actingAsDistributor(User $user): self
    {
        $role = $user->roles->firstWhere('slug', Role::SLUG_DISTRIBUTOR) ?? Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        app(CurrentRoleService::class)->set($user, $role->id);

        return $this->actingAs($user)->withSession(['current_role_id' => $role->id]);
    }

    private function actingAsEmployee(User $user): self
    {
        $user->load('roles');
        $role = $user->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        app(CurrentRoleService::class)->set($user, $role->id);

        return $this->actingAs($user)->withSession(['current_role_id' => $role->id]);
    }
}

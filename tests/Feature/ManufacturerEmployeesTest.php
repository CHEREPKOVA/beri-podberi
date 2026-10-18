<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Mail\EmployeePasswordResetMail;
use App\Models\ManufacturerEmployeeRole;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\Warehouse;
use App\Services\CurrentRoleService;
use App\Services\ManufacturerEmployeeService;
use App\Support\CompanyStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManufacturerEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_open_employees_section(): void
    {
        [$admin] = $this->makeManufacturerAdmin();

        $this->actingAsManufacturer($admin)
            ->get(route('manufacturer.employees.index'))
            ->assertOk()
            ->assertSee('Сотрудники компании')
            ->assertSee('Добавить сотрудника');
    }

    public function test_admin_without_role_in_session_is_not_logged_out(): void
    {
        [$admin] = $this->makeManufacturerAdmin();

        $this->actingAs($admin)
            ->get(route('manufacturer.employees.index'))
            ->assertOk()
            ->assertSee('Сотрудники компании');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(session('current_role_id'));
    }

    public function test_employee_cannot_see_employees_section(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.employees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_invite_employee_and_invitee_activates_access(): void
    {
        Mail::fake();

        [$admin] = $this->makeManufacturerAdmin();
        $role = ManufacturerEmployeeRole::query()->firstOrFail();

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.employees.store'), [
                'name' => 'Иван Петров',
                'email' => 'staff@example.com',
                'employee_role_id' => $role->id,
            ])
            ->assertRedirect(route('manufacturer.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'staff@example.com',
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('role_user', [
            'company_name' => 'Завод Сотрудники',
            'company_status' => CompanyStatus::AWAITING_CONFIRMATION,
        ]);

        $plainToken = null;
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use (&$plainToken) {
            $plainToken = $mail->plainToken;

            return $mail->hasTo('staff@example.com');
        });
        $this->assertNotEmpty($plainToken);

        $this->actingAsManufacturer($admin)
            ->get(route('manufacturer.employees.index'))
            ->assertSee('Ожидает активации')
            ->assertSee('Иван Петров');

        auth()->logout();

        $this->post(route('employee-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $this->assertNotNull(UserInvitation::query()->where('email', 'staff@example.com')->first()?->accepted_at);
    }

    public function test_blocked_employee_cannot_login(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin, 'blocked@example.com', 'OldPass123');

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.employees.block', $employee))
            ->assertRedirect();

        $employee->refresh();
        $this->assertFalse($employee->is_active);

        auth()->logout();

        $this->from('/login')->post('/login', [
            'email' => 'blocked@example.com',
            'password' => 'OldPass123',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['auth']);

        $this->assertSame(
            'Аккаунт деактивирован администратором компании.',
            session('errors')->first('auth')
        );
        $this->assertGuest();
    }

    public function test_password_reset_invalidates_old_password(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin, 'reset@example.com', 'OldPass123');

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.employees.reset_password', $employee))
            ->assertRedirect()
            ->assertSessionHas('success', 'Ссылка для восстановления пароля отправлена на email сотрудника.');

        Mail::assertSent(EmployeePasswordResetMail::class, fn (EmployeePasswordResetMail $mail) => $mail->hasTo('reset@example.com'));

        $this->assertFalse(Hash::check('OldPass123', $employee->fresh()->password));
    }

    public function test_employee_cannot_be_deleted_after_operations(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $warehouse = Warehouse::query()->where('manufacturer_profile_id', $admin->manufacturerProfile->id)->firstOrFail();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $admin->manufacturerProfile->id,
        ]);
        ProductStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 3,
            'stock_updated_by_user_id' => $employee->id,
        ]);

        $this->actingAsManufacturer($admin)
            ->delete(route('manufacturer.employees.destroy', $employee))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $employee->id]);
    }

    public function test_employee_without_history_can_be_deleted(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin, 'delete-me@example.com');

        $this->actingAsManufacturer($admin)
            ->delete(route('manufacturer.employees.destroy', $employee))
            ->assertRedirect(route('manufacturer.employees.index'));

        $this->assertDatabaseMissing('users', ['email' => 'delete-me@example.com']);
    }

    public function test_order_manager_employee_can_open_orders(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin, 'orders-staff@example.com');

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.orders.index'))
            ->assertOk();
    }

    public function test_employee_with_view_products_cannot_manage_them(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin, 'viewer@example.com');

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.products.index'))
            ->assertOk();

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.products.create'))
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.employees.index'))
            ->assertForbidden();
    }

    public function test_employee_sees_only_assigned_warehouses(): void
    {
        Mail::fake();
        [$admin] = $this->makeManufacturerAdmin();
        $profile = $admin->manufacturerProfile;
        $moscow = $profile->warehouses()->first();
        $spb = Warehouse::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'Филиал СПб',
        ]);
        $employee = $this->makeActiveEmployee(
            $admin,
            'wh-staff@example.com',
            'Secret123',
            'Менеджер склада',
            [
                'warehouse_ids' => [$moscow->id],
                'all_warehouses' => '0',
            ],
        );

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.warehouses.index'))
            ->assertOk()
            ->assertSee('Филиал Москва')
            ->assertDontSee('Филиал СПб');

        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
        ]);
        $foreignStock = ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $spb->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $this->actingAsEmployee($employee)
            ->post(route('manufacturer.warehouses.stocks.update'), [
                'product_stock_id' => $foreignStock->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_custom_role(): void
    {
        [$admin] = $this->makeManufacturerAdmin();

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.employees.roles.store'), [
                'name' => 'Менеджер возвратов',
                'permissions' => [
                    'orders' => 'defects',
                    'products' => 'none',
                    'claims' => '1',
                    'discounts' => 'none',
                ],
            ])
            ->assertRedirect(route('manufacturer.employees.index', ['tab' => 'roles']));

        $this->assertDatabaseHas('manufacturer_employee_roles', [
            'name' => 'Менеджер возвратов',
        ]);
    }

    /**
     * @return array{0: User}
     */
    private function makeManufacturerAdmin(): array
    {
        $role = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        $admin = User::factory()->create([
            'name' => 'Админ Завода',
            'email' => 'mfr-admin@example.com',
        ]);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Завод Сотрудники',
            'company_type' => 'manufacturer',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateManufacturerProfile();
        $profile->update(['full_name' => 'Завод Сотрудники', 'short_name' => 'ЗС']);
        Warehouse::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'Филиал Москва',
        ]);

        app(ManufacturerEmployeeService::class)->ensureDefaultRoles($profile);

        return [$admin->fresh(['roles', 'manufacturerProfile'])];
    }

    private function makeActiveEmployee(
        User $admin,
        string $email = 'employee@example.com',
        string $password = 'Secret123',
        string $roleName = '',
        array $extra = [],
    ): User {
        Mail::fake();
        $role = $roleName !== ''
            ? ManufacturerEmployeeRole::query()->where('name', $roleName)->firstOrFail()
            : ManufacturerEmployeeRole::query()->firstOrFail();

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.employees.store'), array_merge([
                'name' => 'Сотрудник Тестов',
                'email' => $email,
                'employee_role_id' => $role->id,
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

    private function actingAsManufacturer(User $user): self
    {
        $role = $user->roles->firstWhere('slug', Role::SLUG_MANUFACTURER) ?? Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
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

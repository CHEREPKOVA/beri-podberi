<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Mail\EmployeePasswordResetMail;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyEmployeeRole;
use App\Models\EndCompanyProfileChange;
use App\Models\PlatformOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\CurrentRoleService;
use App\Services\EndCompanyEmployeeService;
use App\Support\CompanyStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EndCompanyEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_open_employees_section(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();

        $this->actingAsEndCompany($admin)
            ->get(route('end_company.employees.index'))
            ->assertOk()
            ->assertSee('Сотрудники компании')
            ->assertSee('Добавить сотрудника');
    }

    public function test_employee_without_staff_rights_cannot_see_employees_section(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('end_company.employees.index'))
            ->assertForbidden();
    }

    public function test_employee_with_personnel_rights_can_open_employees_section(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $hr = $this->makeActiveEmployee($admin, 'hr@end.example.com', 'Secret123', 'Администратор компании');

        $this->actingAsEmployee($hr)
            ->get(route('end_company.employees.index'))
            ->assertOk()
            ->assertSee('Сотрудники компании');
    }

    public function test_admin_invites_employee_and_invitation_activates_account(): void
    {
        Mail::fake();
        [$admin] = $this->makeEndCompanyAdmin();
        $role = EndCompanyEmployeeRole::query()->where('name', 'Менеджер по закупкам')->firstOrFail();

        $this->actingAsEndCompany($admin)
            ->post(route('end_company.employees.store'), [
                'name' => 'Иван Петров',
                'email' => 'ivan.buyer@example.com',
                'employee_role_id' => $role->id,
                'all_addresses' => '1',
            ])
            ->assertRedirect(route('end_company.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'ivan.buyer@example.com',
            'is_active' => false,
        ]);

        $plainToken = null;
        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) use (&$plainToken) {
            if (! $mail->hasTo('ivan.buyer@example.com')) {
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

        $employee = User::query()->where('email', 'ivan.buyer@example.com')->firstOrFail();
        $this->assertTrue($employee->is_active);
        $this->assertTrue($employee->isEndCompanyStaff());
        $this->assertSame(
            CompanyStatus::ACTIVE,
            strtolower((string) $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE)?->pivot?->company_status)
        );
    }

    public function test_purchasing_manager_can_open_cart_but_not_employees(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('buyer.cart.index'))
            ->assertOk();

        $this->actingAsEmployee($employee)
            ->get(route('buyer.orders.index'))
            ->assertOk();

        $this->actingAsEmployee($employee)
            ->get(route('end_company.employees.index'))
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->get(route('end_company.profile'))
            ->assertForbidden();
    }

    public function test_tech_staff_can_open_catalog_but_not_cart(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $employee = $this->makeActiveEmployee($admin, 'tech@end.example.com', 'Secret123', 'Сотрудник технической службы');

        $this->actingAsEmployee($employee)
            ->get(route('buyer.catalog.index'))
            ->assertOk();

        $this->actingAsEmployee($employee)
            ->get(route('buyer.cart.index'))
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->get(route('buyer.orders.index'))
            ->assertForbidden();
    }

    public function test_admin_can_reset_password_and_block_employee(): void
    {
        Mail::fake();
        [$admin] = $this->makeEndCompanyAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEndCompany($admin)
            ->post(route('end_company.employees.reset_password', $employee))
            ->assertRedirect();

        Mail::assertSent(EmployeePasswordResetMail::class);

        $this->actingAsEndCompany($admin)
            ->post(route('end_company.employees.block', $employee))
            ->assertRedirect();

        $employee->refresh();
        $this->assertFalse($employee->is_active);
    }

    public function test_employee_cannot_be_deleted_after_operational_history(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $employee = $this->makeActiveEmployee($admin);
        $profile = $admin->endCompanyProfile;

        EndCompanyProfileChange::query()->create([
            'end_company_profile_id' => $profile->id,
            'user_id' => $employee->id,
            'section' => 'general',
            'summary' => 'Изменение профиля',
        ]);

        $this->actingAsEndCompany($admin)
            ->delete(route('end_company.employees.destroy', $employee))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $employee->id]);
    }

    public function test_employee_without_history_can_be_deleted(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $employee = $this->makeActiveEmployee($admin, 'delete-me@end.example.com');

        $this->actingAsEndCompany($admin)
            ->delete(route('end_company.employees.destroy', $employee))
            ->assertRedirect(route('end_company.employees.index'));

        $this->assertDatabaseMissing('users', ['email' => 'delete-me@end.example.com']);
    }

    public function test_admin_can_create_custom_role(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();

        $this->actingAsEndCompany($admin)
            ->post(route('end_company.employees.roles.store'), [
                'name' => 'Экономист филиала',
                'permissions' => [
                    'orders' => 'view',
                    'catalog' => '1',
                    'employees' => '1',
                ],
            ])
            ->assertRedirect(route('end_company.employees.index', ['tab' => 'roles']));

        $this->assertDatabaseHas('end_company_employee_roles', [
            'name' => 'Экономист филиала',
        ]);
    }

    public function test_branch_manager_sees_only_own_branch_orders(): void
    {
        [$admin] = $this->makeEndCompanyAdmin();
        $profile = $admin->endCompanyProfile;
        $moscow = $profile->deliveryAddresses()->first();
        $region = Region::factory()->create();
        $spb = EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Филиал СПб',
            'address' => 'Санкт-Петербург',
            'region_id' => $region->id,
            'is_default' => false,
        ]);

        $manager = $this->makeActiveEmployee(
            $admin,
            'branch@example.com',
            'Secret123',
            'Менеджер филиала',
            [
                'all_addresses' => '0',
                'delivery_address_ids' => [$moscow->id],
            ],
        );

        $ownBranch = PlatformOrder::query()->create([
            'order_number' => 'BP-BR-1',
            'source' => PlatformOrder::SOURCE_LK,
            'order_channel' => PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR,
            'end_company_profile_id' => $profile->id,
            'end_company_delivery_address_id' => $moscow->id,
            'created_by_user_id' => $admin->id,
            'total_amount' => 100,
            'status' => PlatformOrder::STATUS_NEW,
            'ordered_at' => now(),
        ]);
        $otherBranch = PlatformOrder::query()->create([
            'order_number' => 'BP-BR-2',
            'source' => PlatformOrder::SOURCE_LK,
            'order_channel' => PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR,
            'end_company_profile_id' => $profile->id,
            'end_company_delivery_address_id' => $spb->id,
            'created_by_user_id' => $admin->id,
            'total_amount' => 200,
            'status' => PlatformOrder::STATUS_NEW,
            'ordered_at' => now(),
        ]);
        $foreignPickup = PlatformOrder::query()->create([
            'order_number' => 'BP-BR-3',
            'source' => PlatformOrder::SOURCE_LK,
            'order_channel' => PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR,
            'end_company_profile_id' => $profile->id,
            'created_by_user_id' => $admin->id,
            'total_amount' => 50,
            'status' => PlatformOrder::STATUS_NEW,
            'ordered_at' => now(),
        ]);

        $this->actingAsEmployee($manager)
            ->get(route('buyer.orders.index'))
            ->assertOk()
            ->assertSee('BP-BR-1')
            ->assertDontSee('BP-BR-2')
            ->assertDontSee('BP-BR-3');

        $this->actingAsEmployee($manager)
            ->get(route('buyer.orders.show', $ownBranch))
            ->assertOk();

        $this->actingAsEmployee($manager)
            ->get(route('buyer.orders.show', $otherBranch))
            ->assertForbidden();

        $this->actingAsEmployee($manager)
            ->get(route('buyer.orders.show', $foreignPickup))
            ->assertForbidden();
    }

    /**
     * @return array{0: User}
     */
    private function makeEndCompanyAdmin(): array
    {
        $role = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $admin = User::factory()->create([
            'name' => 'Админ Конечной',
            'email' => 'end-admin@example.com',
        ]);
        $admin->roles()->attach($role->id, [
            'company_name' => 'СТО Сотрудники',
            'company_type' => 'end_company',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateEndCompanyProfile();
        $profile->update(['full_name' => 'СТО Сотрудники', 'short_name' => 'СТО']);
        $region = Region::factory()->create();
        EndCompanyDeliveryAddress::query()->create([
            'end_company_profile_id' => $profile->id,
            'name' => 'Филиал Москва',
            'address' => 'Москва',
            'region_id' => $region->id,
            'is_default' => true,
        ]);

        app(EndCompanyEmployeeService::class)->ensureDefaultRoles($profile);

        return [$admin->fresh(['roles', 'endCompanyProfile'])];
    }

    private function makeActiveEmployee(
        User $admin,
        string $email = 'end-employee@example.com',
        string $password = 'Secret123',
        string $roleName = 'Менеджер по закупкам',
        array $extra = [],
    ): User {
        Mail::fake();
        $role = EndCompanyEmployeeRole::query()->where('name', $roleName)->firstOrFail();

        $this->actingAsEndCompany($admin)
            ->post(route('end_company.employees.store'), array_merge([
                'name' => 'Сотрудник Компании',
                'email' => $email,
                'employee_role_id' => $role->id,
                'all_addresses' => '1',
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

    private function actingAsEndCompany(User $user): self
    {
        $role = $user->roles->firstWhere('slug', Role::SLUG_END_COMPANY) ?? Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
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

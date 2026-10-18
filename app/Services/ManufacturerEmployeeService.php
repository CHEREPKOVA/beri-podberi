<?php

namespace App\Services;

use App\Mail\EmployeeAccessChangedMail;
use App\Mail\EmployeeInvitationMail;
use App\Mail\EmployeePasswordResetMail;
use App\Models\ManufacturerEmployeeRole;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderClaim;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\CompanyStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManufacturerEmployeeService
{
    public function __construct(
        private readonly ManufacturerCompanyContext $context,
        private readonly ManufacturerStaffAccess $access,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(User $admin): array
    {
        $profile = $this->requireProfile($admin);
        $companyName = $this->requireCompanyName($admin);
        $totalWarehouses = $profile->warehouses()->count();

        $users = User::query()
            ->whereHas('roles', function ($query) use ($companyName): void {
                $query->where('role_user.company_name', $companyName)
                    ->whereIn('roles.slug', [Role::SLUG_MANUFACTURER, Role::SLUG_COMPANY_EMPLOYEE]);
            })
            ->with(['roles' => function ($query) use ($companyName): void {
                $query->where('role_user.company_name', $companyName)
                    ->whereIn('roles.slug', [Role::SLUG_MANUFACTURER, Role::SLUG_COMPANY_EMPLOYEE]);
            }])
            ->orderByDesc('created_at')
            ->get();

        $roleNames = $profile->employeeRoles()->pluck('name', 'id');

        return $users->map(function (User $user) use ($admin, $totalWarehouses, $roleNames, $profile): array {
            $isAdmin = $user->roles->contains('slug', Role::SLUG_MANUFACTURER);
            $params = $this->context->employeeParams($user);
            $roleId = (int) ($params['employee_role_id'] ?? 0);
            $status = $isAdmin ? ManufacturerStaffAccess::STATUS_ACTIVE : $this->access->status($user);

            return [
                'user' => $user,
                'is_admin' => $isAdmin,
                'is_self' => $user->id === $admin->id,
                'role_name' => $isAdmin
                    ? 'Администратор компании'
                    : (string) ($roleNames[$roleId] ?? 'Сотрудник'),
                'status' => $status,
                'status_label' => $this->access->statusLabel($status),
                'warehouse_count' => $isAdmin
                    ? $totalWarehouses
                    : $this->access->warehouseCount($user, $totalWarehouses),
                'added_at' => $user->created_at,
                'can_delete' => ! $isAdmin && ! $this->hasOperationalHistory($user),
                'profile_id' => $profile->id,
            ];
        })->all();
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     employee_role_id: int,
     *     warehouse_ids?: array<int, int>,
     *     all_warehouses?: bool
     * }  $data
     */
    public function invite(User $admin, array $data): UserInvitation
    {
        $profile = $this->requireProfile($admin);
        $companyName = $this->requireCompanyName($admin);
        $email = Str::lower(trim($data['email']));
        $this->assertEmailAvailable($email);

        $role = $this->requireEmployeeRole($profile, (int) $data['employee_role_id']);
        $permissions = $role->normalizedPermissions();
        $warehouseIds = $this->normalizeWarehouseIds($profile, $data);

        return DB::transaction(function () use ($admin, $profile, $companyName, $email, $data, $role, $permissions, $warehouseIds): UserInvitation {
            $employeeRole = Role::findBySlug(Role::SLUG_COMPANY_EMPLOYEE);
            if (! $employeeRole) {
                throw new InvalidArgumentException('Роль сотрудника организации не найдена.');
            }

            $user = User::create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => Str::random(64),
                'is_active' => false,
            ]);

            $params = $this->buildParams((int) $role->id, $permissions, $warehouseIds, $profile->warehouses()->count());

            $user->roles()->attach($employeeRole->id, [
                'company_name' => $companyName,
                'company_type' => 'employee',
                'company_status' => CompanyStatus::AWAITING_CONFIRMATION,
                'company_legal_name' => $profile->full_name ?: $companyName,
                'company_contact_email' => $email,
                'company_params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]);

            $plainToken = UserInvitation::createPlainToken();
            $ttl = (int) config('manufacturer_staff.invitation_ttl_days', UserInvitation::TTL_DAYS);

            $invitation = UserInvitation::create([
                'email' => $email,
                'token' => UserInvitation::hashToken($plainToken),
                'inviter_id' => $admin->id,
                'user_id' => $user->id,
                'manufacturer_profile_id' => $profile->id,
                'employee_role_id' => $role->id,
                'role_id' => $employeeRole->id,
                'name' => $user->name,
                'position' => $role->name,
                'permissions' => $permissions,
                'warehouse_ids' => $warehouseIds,
                'expires_at' => now()->addDays($ttl),
                'sent_at' => now(),
            ]);

            $this->sendInvitationMail($invitation, $plainToken, $profile, $companyName);

            return $invitation;
        });
    }

    public function resendInvitation(User $admin, User $employee): UserInvitation
    {
        $this->assertCompanyEmployee($admin, $employee);

        $invitation = UserInvitation::query()
            ->where('user_id', $employee->id)
            ->whereNull('accepted_at')
            ->latest('id')
            ->first();

        if (! $invitation) {
            throw new InvalidArgumentException('Активное приглашение для сотрудника не найдено.');
        }

        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException('Приглашение уже принято.');
        }

        $plainToken = $invitation->refreshTokenAndExpiry();
        $invitation->forceFill(['sent_at' => now()])->save();

        $profile = $this->requireProfile($admin);
        $this->sendInvitationMail($invitation->fresh(), $plainToken, $profile, $this->requireCompanyName($admin));

        return $invitation->fresh();
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     employee_role_id: int,
     *     warehouse_ids?: array<int, int>,
     *     all_warehouses?: bool
     * }  $data
     */
    public function update(User $admin, User $employee, array $data): User
    {
        $this->assertCompanyEmployee($admin, $employee);
        $profile = $this->requireProfile($admin);
        $role = $this->requireEmployeeRole($profile, (int) $data['employee_role_id']);
        $permissions = $role->normalizedPermissions();
        $warehouseIds = $this->normalizeWarehouseIds($profile, $data);
        $email = Str::lower(trim($data['email']));

        $this->assertEmailAvailable($email, $employee->id);

        $employeeRole = $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        if (! $employeeRole) {
            throw new InvalidArgumentException('Сотрудник не найден.');
        }

        $params = $this->buildParams((int) $role->id, $permissions, $warehouseIds, $profile->warehouses()->count());

        $employee->update([
            'name' => trim($data['name']),
            'email' => $email,
        ]);

        $employee->roles()->updateExistingPivot($employeeRole->id, [
            'company_contact_email' => $email,
            'company_params' => json_encode($params, JSON_UNESCAPED_UNICODE),
        ]);

        UserInvitation::query()
            ->where('user_id', $employee->id)
            ->whereNull('accepted_at')
            ->update([
                'email' => $email,
                'name' => $employee->name,
                'employee_role_id' => $role->id,
                'position' => $role->name,
                'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                'warehouse_ids' => json_encode($warehouseIds, JSON_UNESCAPED_UNICODE),
            ]);

        return $employee->fresh(['roles']);
    }

    public function accept(UserInvitation $invitation, string $password): User
    {
        if (! $invitation->isValid()) {
            throw new InvalidArgumentException('Ссылка приглашения недействительна или просрочена.');
        }

        return DB::transaction(function () use ($invitation, $password): User {
            /** @var User $user */
            $user = User::query()->findOrFail($invitation->user_id);

            $user->update([
                'password' => $password,
                'is_active' => true,
            ]);

            $employeeRole = $user->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
            if ($employeeRole) {
                $user->roles()->updateExistingPivot($employeeRole->id, [
                    'company_status' => CompanyStatus::ACTIVE,
                ]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user->fresh(['roles']);
        });
    }

    public function resetPassword(User $admin, User $employee): void
    {
        $this->assertCompanyEmployee($admin, $employee);

        if ($this->access->status($employee) === ManufacturerStaffAccess::STATUS_PENDING) {
            $this->resendInvitation($admin, $employee);

            return;
        }

        $employee->forceFill([
            'password' => Str::random(64),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $employee->email)->delete();

        $token = Password::broker()->createToken($employee);
        $profile = $this->requireProfile($admin);
        $companyName = $this->requireCompanyName($admin);

        Mail::to($employee->email)->send(new EmployeePasswordResetMail(
            $employee,
            $token,
            $companyName,
            $profile->displayName(),
        ));
    }

    public function block(User $admin, User $employee, bool $notify): void
    {
        $this->assertCompanyEmployee($admin, $employee);

        $employee->update(['is_active' => false]);
        $this->updateCompanyStatus($employee, CompanyStatus::BLOCKED);

        if ($notify) {
            $this->notifyAccessChange($admin, $employee, 'blocked');
        }
    }

    public function unblock(User $admin, User $employee, bool $notify): void
    {
        $this->assertCompanyEmployee($admin, $employee);

        if ($this->access->status($employee) === ManufacturerStaffAccess::STATUS_PENDING) {
            throw new InvalidArgumentException('Сотрудник ещё не активировал приглашение.');
        }

        $employee->update(['is_active' => true]);
        $this->updateCompanyStatus($employee, CompanyStatus::ACTIVE);

        if ($notify) {
            $this->notifyAccessChange($admin, $employee, 'unblocked');
        }
    }

    public function delete(User $admin, User $employee): void
    {
        $this->assertCompanyEmployee($admin, $employee);

        if ($this->hasOperationalHistory($employee)) {
            throw new InvalidArgumentException('Сотрудника нельзя удалить: он уже участвовал в операциях. Заблокируйте доступ.');
        }

        DB::transaction(function () use ($employee): void {
            UserInvitation::query()->where('user_id', $employee->id)->delete();

            $employeeRole = Role::findBySlug(Role::SLUG_COMPANY_EMPLOYEE);
            if ($employeeRole) {
                $employee->roles()->detach($employeeRole->id);
            }

            if ($employee->roles()->count() === 0) {
                $employee->delete();
            }
        });
    }

    public function hasOperationalHistory(User $user): bool
    {
        return PlatformOrder::query()->where('status_changed_by_user_id', $user->id)->exists()
            || PlatformOrder::query()->where('paused_by_user_id', $user->id)->exists()
            || ProductStock::query()->where('stock_updated_by_user_id', $user->id)->exists()
            || PlatformOrderClaim::query()->where('created_by_user_id', $user->id)->exists();
    }

    /**
     * @return list<ManufacturerEmployeeRole>
     */
    public function ensureDefaultRoles(ManufacturerProfile $profile): array
    {
        if ($profile->employeeRoles()->exists()) {
            return $profile->employeeRoles()->get()->all();
        }

        $sort = 1;
        foreach (config('manufacturer_staff.default_roles', []) as $role) {
            $profile->employeeRoles()->create([
                'name' => $role['name'],
                'permissions' => ManufacturerEmployeeRole::normalizePermissions($role['permissions'] ?? []),
                'sort_order' => $sort++,
            ]);
        }

        return $profile->employeeRoles()->get()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRole(User $admin, array $data): ManufacturerEmployeeRole
    {
        $profile = $this->requireProfile($admin);

        return $profile->employeeRoles()->create([
            'name' => trim($data['name']),
            'permissions' => ManufacturerEmployeeRole::normalizePermissions($data['permissions'] ?? []),
            'sort_order' => (int) ($profile->employeeRoles()->max('sort_order') ?? 0) + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRole(User $admin, ManufacturerEmployeeRole $role, array $data): ManufacturerEmployeeRole
    {
        $this->assertRoleOwnership($admin, $role);

        $role->update([
            'name' => trim($data['name']),
            'permissions' => ManufacturerEmployeeRole::normalizePermissions($data['permissions'] ?? []),
        ]);

        return $role->fresh();
    }

    public function deleteRole(User $admin, ManufacturerEmployeeRole $role): void
    {
        $this->assertRoleOwnership($admin, $role);

        $companyName = $this->requireCompanyName($admin);
        $inUse = User::query()
            ->whereHas('roles', function ($query) use ($companyName, $role): void {
                $query->where('roles.slug', Role::SLUG_COMPANY_EMPLOYEE)
                    ->where('role_user.company_name', $companyName)
                    ->where('role_user.company_params->employee_role_id', $role->id);
            })
            ->exists();

        if ($inUse) {
            throw new InvalidArgumentException('Роль назначена сотрудникам. Сначала смените роль у сотрудников.');
        }

        $role->delete();
    }

    public function findValidByToken(string $token): ?UserInvitation
    {
        $invitation = UserInvitation::findByPlainToken($token);

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    public function requireOwnedEmployee(User $admin, User $employee): User
    {
        $this->assertCompanyEmployee($admin, $employee);

        return $employee->load('roles');
    }

    private function requireProfile(User $admin): ManufacturerProfile
    {
        $profile = $this->context->profile($admin);
        if (! $profile) {
            throw new InvalidArgumentException('Профиль производителя не найден.');
        }

        return $profile;
    }

    private function requireCompanyName(User $admin): string
    {
        $name = $this->context->companyName($admin);
        if ($name === null || $name === '') {
            $profile = $this->requireProfile($admin);
            $name = $profile->full_name;
        }

        if ($name === '') {
            throw new InvalidArgumentException('Не удалось определить компанию.');
        }

        return $name;
    }

    private function requireEmployeeRole(ManufacturerProfile $profile, int $roleId): ManufacturerEmployeeRole
    {
        $role = ManufacturerEmployeeRole::query()
            ->where('manufacturer_profile_id', $profile->id)
            ->where('id', $roleId)
            ->first();

        if (! $role) {
            throw new InvalidArgumentException('Выберите роль сотрудника.');
        }

        return $role;
    }

    private function assertCompanyEmployee(User $admin, User $employee): void
    {
        if ($employee->id === $admin->id) {
            throw new InvalidArgumentException('Нельзя изменить собственную учётную запись этим действием.');
        }

        $companyName = $this->requireCompanyName($admin);
        $belongs = $employee->roles->contains(function (Role $role) use ($companyName): bool {
            return $role->slug === Role::SLUG_COMPANY_EMPLOYEE
                && (string) $role->pivot->company_name === $companyName;
        });

        if (! $belongs) {
            abort(404);
        }
    }

    private function assertRoleOwnership(User $admin, ManufacturerEmployeeRole $role): void
    {
        $profile = $this->requireProfile($admin);
        if ((int) $role->manufacturer_profile_id !== (int) $profile->id) {
            abort(404);
        }
    }

    private function assertEmailAvailable(string $email, ?int $exceptUserId = null): void
    {
        $query = User::query()->where('email', $email);
        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Пользователь с этим email уже зарегистрирован.');
        }

        $pending = UserInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->exists();

        if ($pending) {
            throw new InvalidArgumentException('Для этого email уже есть активное приглашение.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function normalizeWarehouseIds(ManufacturerProfile $profile, array $data): array
    {
        $allowed = $profile->warehouses()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allWarehouses = (bool) ($data['all_warehouses'] ?? false);
        if ($allWarehouses || $allowed === []) {
            return $allowed;
        }

        $selected = array_values(array_unique(array_map('intval', $data['warehouse_ids'] ?? [])));

        return array_values(array_intersect($selected, $allowed));
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @param  list<int>  $warehouseIds
     * @return array<string, mixed>
     */
    private function buildParams(int $roleId, array $permissions, array $warehouseIds, int $totalWarehouses): array
    {
        return [
            'kind' => config('manufacturer_staff.params_kind'),
            'employee_role_id' => $roleId,
            'permissions' => $permissions,
            'warehouse_ids' => $warehouseIds,
            'all_warehouses' => $totalWarehouses > 0 && count($warehouseIds) === $totalWarehouses,
        ];
    }

    private function updateCompanyStatus(User $employee, string $status): void
    {
        $employeeRole = $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        if (! $employeeRole) {
            return;
        }

        $employee->roles()->updateExistingPivot($employeeRole->id, [
            'company_status' => $status,
        ]);
    }

    private function notifyAccessChange(User $admin, User $employee, string $action): void
    {
        $profile = $this->requireProfile($admin);
        $companyName = $this->requireCompanyName($admin);

        Mail::to($employee->email)->send(new EmployeeAccessChangedMail(
            $employee,
            $companyName,
            $profile->displayName(),
            $action,
        ));
    }

    private function sendInvitationMail(
        UserInvitation $invitation,
        string $plainToken,
        ManufacturerProfile $profile,
        string $companyName,
    ): void {
        Mail::to($invitation->email)->send(new EmployeeInvitationMail(
            $invitation,
            $plainToken,
            $companyName,
            $profile->displayName(),
        ));
    }
}

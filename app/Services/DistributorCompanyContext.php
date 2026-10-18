<?php

namespace App\Services;

use App\Models\DistributorProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DistributorCompanyContext
{
    public function isCompanyAdmin(User $user): bool
    {
        $role = $user->getCurrentRole();
        if ($role?->slug === Role::SLUG_DISTRIBUTOR) {
            return true;
        }

        return $user->roles->contains('slug', Role::SLUG_DISTRIBUTOR)
            && $role === null;
    }

    public function isDistributorEmployee(User $user): bool
    {
        $employeeRole = $user->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        if (! $employeeRole) {
            return false;
        }

        $params = $this->decodeParams($employeeRole->pivot->company_params ?? null);
        $kind = (string) ($params['kind'] ?? '');
        if ($kind !== '' && $kind !== config('distributor_staff.params_kind')) {
            return false;
        }
        if ($kind === config('distributor_staff.params_kind')) {
            return true;
        }

        $companyName = trim((string) ($employeeRole->pivot->company_name ?? ''));

        return $companyName !== '' && $this->ownerUserIdForCompany($companyName) !== null;
    }

    public function isDistributorStaff(User $user): bool
    {
        return $this->isCompanyAdmin($user) || $this->isDistributorEmployee($user);
    }

    public function companyName(User $user): ?string
    {
        $current = $user->getCurrentRole();
        if ($current) {
            $name = trim((string) ($user->roles->firstWhere('id', $current->id)?->pivot?->company_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        foreach ([Role::SLUG_DISTRIBUTOR, Role::SLUG_COMPANY_EMPLOYEE] as $slug) {
            $name = trim((string) ($user->roles->firstWhere('slug', $slug)?->pivot?->company_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $profileName = trim((string) ($user->distributorProfile()->value('full_name') ?? ''));

        return $profileName !== '' ? $profileName : null;
    }

    public function profile(User $user): ?DistributorProfile
    {
        $own = $user->distributorProfile()->first();
        if ($own) {
            return $own;
        }

        return $this->employerProfile($user);
    }

    public function employerProfile(User $user): ?DistributorProfile
    {
        $companyName = $this->companyName($user);
        if ($companyName === null) {
            return null;
        }

        $ownerId = $this->ownerUserIdForCompany($companyName);
        if ($ownerId === null) {
            return null;
        }

        return DistributorProfile::query()->where('user_id', $ownerId)->first();
    }

    public function ownerUserIdForCompany(string $companyName): ?int
    {
        $ownerId = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.slug', Role::SLUG_DISTRIBUTOR)
            ->where('role_user.company_name', $companyName)
            ->value('role_user.user_id');

        return $ownerId !== null ? (int) $ownerId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function employeeParams(User $user): array
    {
        $employeeRole = $user->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        if (! $employeeRole) {
            return [];
        }

        return $this->decodeParams($employeeRole->pivot->company_params ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeParams(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}

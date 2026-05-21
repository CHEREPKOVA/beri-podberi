<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class ManufacturerStaffPermissions
{
    public function staffType(User $user): string
    {
        $role = $user->getCurrentRole();
        if (! $role) {
            return 'warehouse';
        }

        if ($role->slug === Role::SLUG_MANUFACTURER) {
            return 'admin';
        }

        if ($role->slug === Role::SLUG_COMPANY_EMPLOYEE) {
            $pivot = $user->roles->firstWhere('id', $role->id)?->pivot;
            $type = strtolower((string) ($pivot?->company_type ?? ''));

            if (array_key_exists($type, config('manufacturer_staff.partner_catalog', []))) {
                return $type;
            }
        }

        return 'warehouse';
    }

    public function can(User $user, string $ability): bool
    {
        $type = $this->staffType($user);
        $abilities = config("manufacturer_staff.partner_catalog.{$type}", []);

        return in_array($ability, $abilities, true);
    }

    public function canViewPartnerCatalog(User $user): bool
    {
        return $this->can($user, 'view');
    }

    public function canAddPartner(User $user): bool
    {
        return $this->can($user, 'add');
    }

    public function canRemovePartner(User $user): bool
    {
        return $this->can($user, 'remove');
    }

    public function canAssignExclusive(User $user): bool
    {
        return $this->can($user, 'exclusive');
    }

    public function canViewOrders(User $user): bool
    {
        return $this->can($user, 'orders');
    }
}

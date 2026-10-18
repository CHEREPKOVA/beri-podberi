<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class ManufacturerStaffPermissions
{
    public function __construct(
        private readonly ManufacturerStaffAccess $access,
        private readonly ManufacturerCompanyContext $context,
    ) {}

    public function staffType(User $user): string
    {
        if ($this->context->isCompanyAdmin($user)) {
            return 'admin';
        }

        $role = $user->getCurrentRole();
        if ($role?->slug === Role::SLUG_COMPANY_EMPLOYEE) {
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
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        if (! $this->context->isManufacturerEmployee($user)) {
            $type = $this->staffType($user);
            $abilities = config("manufacturer_staff.partner_catalog.{$type}", []);

            return in_array($ability, $abilities, true);
        }

        return match ($ability) {
            'view' => $this->access->canAccessSection($user, 'partners') || $this->access->canAccessSection($user, 'orders'),
            'add', 'remove', 'exclusive' => $this->access->canAccessSection($user, 'partners'),
            'orders' => $this->access->canAccessSection($user, 'orders'),
            default => false,
        };
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

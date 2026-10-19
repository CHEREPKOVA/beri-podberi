<?php

namespace App\Services;

use App\Models\DistributorEmployeeRole;
use App\Models\DistributorWarehouse;
use App\Models\PlatformOrder;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DistributorStaffAccess
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public function __construct(
        private readonly DistributorCompanyContext $context,
    ) {}

    public function canManageEmployees(User $user): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        if (! $this->context->isDistributorEmployee($user)) {
            return false;
        }

        return (bool) $this->permissions($user)['employees'];
    }

    public function canAccessCabinet(User $user): bool
    {
        return $this->context->isDistributorStaff($user);
    }

    public function canAccessSection(User $user, string $section): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        if (! $this->context->isDistributorEmployee($user)) {
            return false;
        }

        $permissions = $this->permissions($user);

        return match ($section) {
            'employees' => (bool) $permissions['employees'],
            'profile' => (bool) $permissions['company_profile'],
            'warehouses' => (bool) $permissions['warehouses'],
            'products' => in_array($permissions['products'], ['view', 'manage'], true),
            'products.manage' => $permissions['products'] === 'manage',
            'products.prices' => (bool) $permissions['prices'] || $permissions['products'] === 'manage',
            'products.import' => (bool) $permissions['import'],
            'orders' => $permissions['orders'] !== 'none',
            'orders.manage' => $permissions['orders'] === 'manage',
            'purchases' => $permissions['purchases'] !== 'none',
            'purchases.manage' => $permissions['purchases'] === 'manage',
            'manufacturer_chat' => (bool) $permissions['manufacturer_chat'],
            'catalog' => $permissions['purchases'] !== 'none',
            'discounts' => (bool) $permissions['discounts'],
            'discounts.manage' => (bool) $permissions['discounts'],
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function permissions(User $user): array
    {
        if ($this->context->isCompanyAdmin($user)) {
            return [
                'orders' => 'manage',
                'purchases' => 'manage',
                'products' => 'manage',
                'prices' => true,
                'warehouses' => true,
                'employees' => true,
                'discounts' => true,
                'import' => true,
                'manufacturer_chat' => true,
                'company_profile' => true,
            ];
        }

        $params = $this->context->employeeParams($user);
        $roleId = (int) ($params['employee_role_id'] ?? 0);
        $profile = $this->context->profile($user);

        if ($profile && $roleId > 0) {
            $role = DistributorEmployeeRole::query()
                ->where('distributor_profile_id', $profile->id)
                ->whereKey($roleId)
                ->first();

            if ($role) {
                return $role->normalizedPermissions();
            }
        }

        return DistributorEmployeeRole::normalizePermissions(
            is_array($params['permissions'] ?? null) ? $params['permissions'] : []
        );
    }

    /**
     * @return list<int>
     */
    public function warehouseIds(User $user): array
    {
        if ($this->hasAllWarehouses($user)) {
            $profile = $this->context->profile($user);
            if (! $profile) {
                return [];
            }

            return $profile->warehouses()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $params = $this->context->employeeParams($user);
        $ids = $params['warehouse_ids'] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function hasAllWarehouses(User $user): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        $params = $this->context->employeeParams($user);

        return (bool) ($params['all_warehouses'] ?? false);
    }

    public function canAccessWarehouse(User $user, int $warehouseId): bool
    {
        if ($this->hasAllWarehouses($user)) {
            return true;
        }

        return in_array($warehouseId, $this->warehouseIds($user), true);
    }

    /**
     * @return Collection<int, DistributorWarehouse>
     */
    public function visibleWarehouses(User $user): Collection
    {
        $profile = $this->context->profile($user);
        if (! $profile) {
            return collect();
        }

        $query = $profile->warehouses()->with('region')->orderBy('name');
        if (! $this->hasAllWarehouses($user)) {
            $ids = $this->warehouseIds($user);
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        return $query->get();
    }

    /**
     * @param  Builder<PlatformOrder>  $query
     */
    public function constrainOrdersToWarehouses(User $user, Builder $query): void
    {
        if ($this->hasAllWarehouses($user)) {
            return;
        }

        $ids = $this->warehouseIds($user);
        $query->where(function (Builder $inner) use ($ids): void {
            $inner->whereIn('distributor_warehouse_id', $ids === [] ? [0] : $ids)
                ->orWhereNull('distributor_warehouse_id');
        });
    }

    public function warehouseCount(User $user, int $totalWarehouses): int
    {
        if ($this->hasAllWarehouses($user) || $this->context->isCompanyAdmin($user)) {
            return $totalWarehouses;
        }

        return count($this->warehouseIds($user));
    }

    public function status(User $user): string
    {
        if ($this->context->isCompanyAdmin($user)) {
            return $user->is_active ? self::STATUS_ACTIVE : self::STATUS_BLOCKED;
        }

        $employeeRole = $user->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        $companyStatus = strtolower((string) ($employeeRole?->pivot?->company_status ?? ''));

        if ($companyStatus === CompanyStatus::BLOCKED || (! $user->is_active && $companyStatus === CompanyStatus::ACTIVE)) {
            return self::STATUS_BLOCKED;
        }

        if (! $user->is_active || $companyStatus === CompanyStatus::AWAITING_CONFIRMATION) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_ACTIVE;
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Ожидает активации',
            self::STATUS_BLOCKED => 'Заблокирован',
            default => 'Активен',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function sidebarState(User $user): array
    {
        $isStaff = $this->canAccessCabinet($user);

        return [
            'is_staff' => $isStaff,
            'is_admin' => $this->context->isCompanyAdmin($user),
            'can_employees' => $isStaff && $this->canManageEmployees($user),
            'can_profile' => $isStaff && $this->canAccessSection($user, 'profile'),
            'can_warehouses' => $isStaff && $this->canAccessSection($user, 'warehouses'),
            'can_products' => $isStaff && $this->canAccessSection($user, 'products'),
            'can_orders' => $isStaff && $this->canAccessSection($user, 'orders'),
            'can_purchases' => $isStaff && $this->canAccessSection($user, 'purchases'),
            'can_catalog' => $isStaff && $this->canAccessSection($user, 'catalog'),
            'can_discounts' => $isStaff && $this->canAccessSection($user, 'discounts'),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ManufacturerEmployeeRole;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyStatus;
use Illuminate\Support\Collection;

class ManufacturerStaffAccess
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public function __construct(
        private readonly ManufacturerCompanyContext $context,
    ) {}

    public function canManageEmployees(User $user): bool
    {
        return $this->context->isCompanyAdmin($user);
    }

    public function canAccessCabinet(User $user): bool
    {
        return $this->context->isManufacturerStaff($user);
    }

    public function canAccessSection(User $user, string $section): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        if (! $this->context->isManufacturerEmployee($user)) {
            return false;
        }

        $permissions = $this->permissions($user);

        return match ($section) {
            'employees' => false,
            'profile' => (bool) $permissions['company_profile'],
            'warehouses' => (bool) $permissions['warehouses'],
            'products' => in_array($permissions['products'], ['view', 'manage'], true),
            'products.manage' => $permissions['products'] === 'manage',
            'orders' => $permissions['orders'] !== 'none',
            'orders.create' => in_array($permissions['orders'], ['create', 'confirm', 'defects'], true),
            'orders.confirm' => in_array($permissions['orders'], ['confirm', 'defects'], true),
            'orders.defects' => $permissions['orders'] === 'defects',
            'claims' => (bool) $permissions['claims'] || $permissions['orders'] === 'defects',
            'partners' => (bool) $permissions['company_profile'],
            'catalog' => true,
            'discounts' => in_array($permissions['discounts'] ?? 'none', ['view', 'manage'], true),
            'discounts.manage' => ($permissions['discounts'] ?? 'none') === 'manage',
            'discounts.delete' => false,
            default => false,
        };
    }

    /**
     * @return array{
     *     orders: string,
     *     products: string,
     *     warehouses: bool,
     *     company_profile: bool,
     *     claims: bool,
     *     discounts: string
     * }
     */
    public function permissions(User $user): array
    {
        if ($this->context->isCompanyAdmin($user)) {
            return [
                'orders' => 'defects',
                'products' => 'manage',
                'warehouses' => true,
                'company_profile' => true,
                'claims' => true,
                'discounts' => 'manage',
            ];
        }

        $params = $this->context->employeeParams($user);
        $roleId = (int) ($params['employee_role_id'] ?? 0);
        $profile = $this->context->profile($user);

        if ($profile && $roleId > 0) {
            $role = ManufacturerEmployeeRole::query()
                ->where('manufacturer_profile_id', $profile->id)
                ->whereKey($roleId)
                ->first();

            if ($role) {
                return $role->normalizedPermissions();
            }
        }

        return ManufacturerEmployeeRole::normalizePermissions(
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
     * @return Collection<int, Warehouse>
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
        $isAdmin = $this->canManageEmployees($user);

        return [
            'is_staff' => $isStaff,
            'is_admin' => $isAdmin,
            'can_employees' => $isAdmin,
            'can_profile' => $isStaff && $this->canAccessSection($user, 'profile'),
            'can_warehouses' => $isStaff && $this->canAccessSection($user, 'warehouses'),
            'can_products' => $isStaff && $this->canAccessSection($user, 'products'),
            'can_orders' => $isStaff && $this->canAccessSection($user, 'orders'),
            'can_partners' => $isStaff && $this->canAccessSection($user, 'partners'),
            'can_catalog' => $isStaff,
            'can_discounts' => $isStaff && $this->canAccessSection($user, 'discounts'),
        ];
    }
}

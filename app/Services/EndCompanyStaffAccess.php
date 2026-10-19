<?php

namespace App\Services;

use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyEmployeeRole;
use App\Models\PlatformOrder;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EndCompanyStaffAccess
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public function __construct(
        private readonly EndCompanyCompanyContext $context,
    ) {}

    public function canManageEmployees(User $user): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        if (! $this->context->isEndCompanyEmployee($user)) {
            return false;
        }

        return (bool) $this->permissions($user)['employees'];
    }

    public function canAccessCabinet(User $user): bool
    {
        return $this->context->isEndCompanyStaff($user);
    }

    public function canAccessSection(User $user, string $section): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        if (! $this->context->isEndCompanyEmployee($user)) {
            return false;
        }

        $permissions = $this->permissions($user);

        return match ($section) {
            'employees' => (bool) $permissions['employees'],
            'profile' => (bool) $permissions['company_profile'],
            'catalog' => (bool) $permissions['catalog'],
            'cart' => in_array($permissions['orders'], ['create', 'manage'], true),
            'orders' => $permissions['orders'] !== 'none',
            'orders.create' => in_array($permissions['orders'], ['create', 'manage'], true),
            'orders.manage' => $permissions['orders'] === 'manage',
            'end_company' => $permissions['orders'] !== 'none' || (bool) $permissions['catalog'],
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
                'own_orders_only' => false,
                'catalog' => true,
                'company_profile' => true,
                'employees' => true,
            ];
        }

        $params = $this->context->employeeParams($user);
        $roleId = (int) ($params['employee_role_id'] ?? 0);
        $profile = $this->context->profile($user);

        if ($profile && $roleId > 0) {
            $role = EndCompanyEmployeeRole::query()
                ->where('end_company_profile_id', $profile->id)
                ->whereKey($roleId)
                ->first();

            if ($role) {
                return $role->normalizedPermissions();
            }
        }

        return EndCompanyEmployeeRole::normalizePermissions(
            is_array($params['permissions'] ?? null) ? $params['permissions'] : []
        );
    }

    public function seesOnlyOwnOrders(User $user): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return false;
        }

        return (bool) $this->permissions($user)['own_orders_only'];
    }

    /**
     * @return list<int>
     */
    public function deliveryAddressIds(User $user): array
    {
        if ($this->hasAllAddresses($user)) {
            $profile = $this->context->profile($user);
            if (! $profile) {
                return [];
            }

            return $profile->deliveryAddresses()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $params = $this->context->employeeParams($user);
        $ids = $params['delivery_address_ids'] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function hasAllAddresses(User $user): bool
    {
        if ($this->context->isCompanyAdmin($user)) {
            return true;
        }

        $params = $this->context->employeeParams($user);

        return (bool) ($params['all_addresses'] ?? false);
    }

    public function canAccessAddress(User $user, int $addressId): bool
    {
        if ($this->hasAllAddresses($user)) {
            return true;
        }

        return in_array($addressId, $this->deliveryAddressIds($user), true);
    }

    /**
     * @return Collection<int, EndCompanyDeliveryAddress>
     */
    public function visibleAddresses(User $user): Collection
    {
        $profile = $this->context->profile($user);
        if (! $profile) {
            return collect();
        }

        $query = $profile->deliveryAddresses()->with(['region', 'contact'])->orderByDesc('is_default')->orderBy('name');
        if (! $this->hasAllAddresses($user)) {
            $ids = $this->deliveryAddressIds($user);
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        return $query->get();
    }

    /**
     * @param  Builder<PlatformOrder>  $query
     */
    public function constrainOrders(User $user, Builder $query): void
    {
        if ($this->seesOnlyOwnOrders($user)) {
            $query->where('created_by_user_id', $user->id);
        }

        if ($this->hasAllAddresses($user)) {
            return;
        }

        $ids = $this->deliveryAddressIds($user);
        $query->where(function (Builder $inner) use ($ids, $user): void {
            $inner->whereIn('end_company_delivery_address_id', $ids === [] ? [0] : $ids);

            if ($ids !== []) {
                $inner->orWhere(function (Builder $ownPickup) use ($user): void {
                    $ownPickup->whereNull('end_company_delivery_address_id')
                        ->where('created_by_user_id', $user->id);
                });
            }
        });
    }

    public function canViewOrder(User $user, PlatformOrder $order): bool
    {
        $profile = $this->context->profile($user);
        if (! $profile || (int) $order->end_company_profile_id !== (int) $profile->id) {
            return false;
        }

        if ($this->seesOnlyOwnOrders($user) && (int) $order->created_by_user_id !== (int) $user->id) {
            return false;
        }

        if ($this->hasAllAddresses($user)) {
            return true;
        }

        if ($order->end_company_delivery_address_id === null) {
            return (int) $order->created_by_user_id === (int) $user->id;
        }

        return $this->canAccessAddress($user, (int) $order->end_company_delivery_address_id);
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
            'can_catalog' => $isStaff && $this->canAccessSection($user, 'catalog'),
            'can_cart' => $isStaff && $this->canAccessSection($user, 'cart'),
            'can_orders' => $isStaff && $this->canAccessSection($user, 'orders'),
        ];
    }
}

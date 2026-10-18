<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorEmployeeRole extends Model
{
    protected $fillable = [
        'distributor_profile_id',
        'name',
        'permissions',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedPermissions(): array
    {
        return self::normalizePermissions($this->permissions ?? []);
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @return array{
     *     orders: string,
     *     purchases: string,
     *     products: string,
     *     prices: bool,
     *     warehouses: bool,
     *     employees: bool,
     *     discounts: bool,
     *     import: bool,
     *     manufacturer_chat: bool,
     *     company_profile: bool
     * }
     */
    public static function normalizePermissions(array $permissions): array
    {
        $defaults = config('distributor_staff.default_permissions', []);
        $orderKeys = array_keys(config('distributor_staff.orders', []));
        $purchaseKeys = array_keys(config('distributor_staff.purchases', []));
        $productKeys = array_keys(config('distributor_staff.products', []));

        $orders = (string) ($permissions['orders'] ?? $defaults['orders'] ?? 'none');
        $purchases = (string) ($permissions['purchases'] ?? $defaults['purchases'] ?? 'none');
        $products = (string) ($permissions['products'] ?? $defaults['products'] ?? 'none');

        return [
            'orders' => in_array($orders, $orderKeys, true) ? $orders : 'none',
            'purchases' => in_array($purchases, $purchaseKeys, true) ? $purchases : 'none',
            'products' => in_array($products, $productKeys, true) ? $products : 'none',
            'prices' => (bool) ($permissions['prices'] ?? $defaults['prices'] ?? false),
            'warehouses' => (bool) ($permissions['warehouses'] ?? $defaults['warehouses'] ?? false),
            'employees' => (bool) ($permissions['employees'] ?? $defaults['employees'] ?? false),
            'discounts' => (bool) ($permissions['discounts'] ?? $defaults['discounts'] ?? false),
            'import' => (bool) ($permissions['import'] ?? $defaults['import'] ?? false),
            'manufacturer_chat' => (bool) ($permissions['manufacturer_chat'] ?? $defaults['manufacturer_chat'] ?? false),
            'company_profile' => (bool) ($permissions['company_profile'] ?? $defaults['company_profile'] ?? false),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerEmployeeRole extends Model
{
    protected $fillable = [
        'manufacturer_profile_id',
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

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
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
     *     products: string,
     *     warehouses: bool,
     *     company_profile: bool,
     *     claims: bool,
     *     discounts: string
     * }
     */
    public static function normalizePermissions(array $permissions): array
    {
        $defaults = config('manufacturer_staff.default_permissions', []);
        $orderKeys = array_keys(config('manufacturer_staff.orders', []));
        $productKeys = array_keys(config('manufacturer_staff.products', []));
        $discountKeys = array_keys(config('manufacturer_staff.discounts', []));

        $orders = (string) ($permissions['orders'] ?? $defaults['orders'] ?? 'none');
        $products = (string) ($permissions['products'] ?? $defaults['products'] ?? 'none');
        $discounts = (string) ($permissions['discounts'] ?? $defaults['discounts'] ?? 'none');

        return [
            'orders' => in_array($orders, $orderKeys, true) ? $orders : 'none',
            'products' => in_array($products, $productKeys, true) ? $products : 'none',
            'warehouses' => (bool) ($permissions['warehouses'] ?? $defaults['warehouses'] ?? false),
            'company_profile' => (bool) ($permissions['company_profile'] ?? $defaults['company_profile'] ?? false),
            'claims' => (bool) ($permissions['claims'] ?? $defaults['claims'] ?? false),
            'discounts' => in_array($discounts, $discountKeys, true) ? $discounts : 'none',
        ];
    }
}

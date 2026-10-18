<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndCompanyEmployeeRole extends Model
{
    protected $fillable = [
        'end_company_profile_id',
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

    public function endCompanyProfile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class);
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
     *     own_orders_only: bool,
     *     catalog: bool,
     *     company_profile: bool,
     *     employees: bool
     * }
     */
    public static function normalizePermissions(array $permissions): array
    {
        $defaults = config('end_company_staff.default_permissions', []);
        $orderKeys = array_keys(config('end_company_staff.orders', []));
        $orders = (string) ($permissions['orders'] ?? $defaults['orders'] ?? 'none');

        return [
            'orders' => in_array($orders, $orderKeys, true) ? $orders : 'none',
            'own_orders_only' => (bool) ($permissions['own_orders_only'] ?? $defaults['own_orders_only'] ?? false),
            'catalog' => (bool) ($permissions['catalog'] ?? $defaults['catalog'] ?? false),
            'company_profile' => (bool) ($permissions['company_profile'] ?? $defaults['company_profile'] ?? false),
            'employees' => (bool) ($permissions['employees'] ?? $defaults['employees'] ?? false),
        ];
    }
}

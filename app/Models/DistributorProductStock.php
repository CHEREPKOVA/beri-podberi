<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorProductStock extends Model
{
    protected $fillable = [
        'distributor_product_id',
        'distributor_warehouse_id',
        'quantity',
        'reserved',
        'stock_updated_at',
        'stock_updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'stock_updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DistributorProduct::class, 'distributor_product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(DistributorWarehouse::class, 'distributor_warehouse_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stock_updated_by_user_id');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }
}

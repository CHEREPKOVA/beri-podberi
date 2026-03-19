<?php

namespace App\Models;

use Database\Factories\ProductStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<ProductStockFactory> */
class ProductStock extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved',
        'stock_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'stock_updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }
}

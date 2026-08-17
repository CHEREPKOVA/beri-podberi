<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformOrderItem extends Model
{
    protected $fillable = [
        'platform_order_id',
        'distributor_product_id',
        'product_id',
        'distributor_warehouse_id',
        'name',
        'sku',
        'manufacturer_name',
        'quantity',
        'pack_quantity',
        'min_order_quantity',
        'unit_price',
        'list_unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'pack_quantity' => 'integer',
            'min_order_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'list_unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function multiplicityLabel(): ?string
    {
        if ($this->pack_quantity !== null && $this->pack_quantity > 1) {
            return 'Кратность: '.$this->pack_quantity;
        }

        if ($this->min_order_quantity !== null && $this->min_order_quantity > 1) {
            return 'Мин. заказ: '.$this->min_order_quantity;
        }

        return null;
    }

    public function thumbnailUrl(): ?string
    {
        $product = $this->product;
        if ($product === null) {
            return null;
        }

        $image = $product->relationLoaded('images')
            ? ($product->images->firstWhere('is_primary', true) ?? $product->images->first())
            : $product->primaryImage();

        return $image?->url;
    }

    public function catalogUrl(): ?string
    {
        if ($this->product_id === null) {
            return null;
        }

        return route('buyer.catalog.show', $this->product_id);
    }

    public function hasDiscount(): bool
    {
        return $this->list_unit_price !== null
            && (float) $this->list_unit_price > (float) $this->unit_price;
    }

    public function discountAmount(): float
    {
        if (! $this->hasDiscount()) {
            return 0.0;
        }

        return round(((float) $this->list_unit_price - (float) $this->unit_price) * $this->quantity, 2);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PlatformOrder::class, 'platform_order_id');
    }

    public function distributorProduct(): BelongsTo
    {
        return $this->belongsTo(DistributorProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(DistributorWarehouse::class, 'distributor_warehouse_id');
    }
}

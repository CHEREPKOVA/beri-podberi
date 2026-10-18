<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerDiscountSetTier extends Model
{
    protected $fillable = [
        'discount_set_id',
        'min_amount',
        'min_quantity',
        'discount_percent',
        'discount_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'min_quantity' => 'integer',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function discountSet(): BelongsTo
    {
        return $this->belongsTo(ManufacturerDiscountSet::class, 'discount_set_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorProductPriceHistory extends Model
{
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_RETAIL = 'retail';

    protected $fillable = [
        'distributor_product_id',
        'price_type',
        'old_price',
        'new_price',
        'comment',
        'effective_at',
        'changed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PURCHASE => 'Закупочная',
            self::TYPE_RETAIL => 'Отпускная',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->price_type] ?? $this->price_type;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DistributorProduct::class, 'distributor_product_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributorPurchaseCart extends Model
{
    protected $fillable = [
        'distributor_profile_id',
        'promo_codes',
    ];

    protected function casts(): array
    {
        return [
            'promo_codes' => 'array',
        ];
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributorPurchaseCartItem::class, 'cart_id');
    }
}

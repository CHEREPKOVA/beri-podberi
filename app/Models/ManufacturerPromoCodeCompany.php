<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerPromoCodeCompany extends Model
{
    protected $fillable = [
        'promo_code_id',
        'company_kind',
        'company_id',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(ManufacturerPromoCode::class, 'promo_code_id');
    }
}

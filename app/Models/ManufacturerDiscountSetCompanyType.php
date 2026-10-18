<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerDiscountSetCompanyType extends Model
{
    protected $fillable = [
        'discount_set_id',
        'company_type',
    ];

    public function discountSet(): BelongsTo
    {
        return $this->belongsTo(ManufacturerDiscountSet::class, 'discount_set_id');
    }

    public function label(): string
    {
        return DistributorProfile::legalFormLabels()[$this->company_type]
            ?? (config('manufacturer_staff.types')[$this->company_type] ?? $this->company_type);
    }
}

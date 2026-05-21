<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndCompanyDeliveryAddress extends Model
{
    protected $fillable = [
        'end_company_profile_id',
        'name',
        'address',
        'region_id',
        'contact_person',
        'phone',
        'working_hours',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class, 'end_company_profile_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}

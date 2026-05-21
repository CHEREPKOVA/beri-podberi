<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerDistributorExclusiveRegion extends Model
{
    protected $fillable = [
        'manufacturer_profile_id',
        'distributor_profile_id',
        'region_id',
        'assigned_by_user_id',
    ];

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}

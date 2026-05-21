<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorContact extends Model
{
    protected $fillable = [
        'distributor_profile_id',
        'full_name',
        'position',
        'email',
        'phone',
        'is_primary',
        'department',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class, 'distributor_profile_id');
    }

    public function canBeDeleted(): bool
    {
        return ! $this->is_primary;
    }
}

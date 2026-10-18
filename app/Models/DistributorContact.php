<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorContact extends Model
{
    protected $fillable = [
        'distributor_profile_id',
        'user_id',
        'full_name',
        'position',
        'email',
        'phone',
        'is_primary',
        'is_active',
        'department',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        if ($this->is_active !== false) {
            return $this->full_name;
        }

        return $this->full_name.' (Неактивный менеджер)';
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

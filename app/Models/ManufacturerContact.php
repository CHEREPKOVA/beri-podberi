<?php

namespace App\Models;

use Database\Factories\ManufacturerContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<ManufacturerContactFactory> */
class ManufacturerContact extends Model
{
    use HasFactory;
    protected $fillable = [
        'manufacturer_profile_id',
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
        return $this->belongsTo(ManufacturerProfile::class, 'manufacturer_profile_id');
    }

    public function canBeDeleted(): bool
    {
        return !$this->is_primary;
    }
}

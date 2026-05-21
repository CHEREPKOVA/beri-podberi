<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorWarehouse extends Model
{
    public const TYPE_MAIN = 'main';

    public const TYPE_REGIONAL = 'regional';

    public const TYPE_STORE = 'store';

    protected $fillable = [
        'distributor_profile_id',
        'name',
        'address',
        'region_id',
        'type',
        'responsible_person',
        'phone',
        'notes',
        'working_hours',
        'shipping_conditions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_MAIN => 'Основной',
            self::TYPE_REGIONAL => 'Региональный',
            self::TYPE_STORE => 'Склад-магазин',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class, 'distributor_profile_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

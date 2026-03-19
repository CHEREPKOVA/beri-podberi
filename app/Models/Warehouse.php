<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<WarehouseFactory> */
class Warehouse extends Model
{
    use HasFactory;
    /** Основной склад */
    public const TYPE_MAIN = 'main';
    /** Временный склад */
    public const TYPE_TEMPORARY = 'temporary';
    /** Транзитный склад */
    public const TYPE_TRANSIT = 'transit';

    protected $fillable = [
        'manufacturer_profile_id',
        'name',
        'address',
        'region_id',
        'type',
        'responsible_person',
        'phone',
        'notes',
        'working_hours',
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
            self::TYPE_TEMPORARY => 'Временный',
            self::TYPE_TRANSIT => 'Транзитный',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class, 'manufacturer_profile_id');
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

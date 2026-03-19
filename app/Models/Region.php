<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'code',
        'federal_district',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function manufacturerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ManufacturerProfile::class, 'manufacturer_region')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDistrict($query, string $district)
    {
        return $query->where('federal_district', $district);
    }

    public static function federalDistricts(): array
    {
        return [
            'Центральный',
            'Северо-Западный',
            'Южный',
            'Северо-Кавказский',
            'Приволжский',
            'Уральский',
            'Сибирский',
            'Дальневосточный',
        ];
    }
}

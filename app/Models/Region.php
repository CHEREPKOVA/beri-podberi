<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'federal_district',
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

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /** Регионы привязки товаров (доступность по региону). */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_region')->withTimestamps();
    }

    public function regionalPrices(): HasMany
    {
        return $this->hasMany(ProductRegionalPrice::class);
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

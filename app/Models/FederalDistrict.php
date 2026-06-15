<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FederalDistrict extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public static function activeNames(): array
    {
        if (! Schema::hasTable('federal_districts')) {
            return Region::fallbackFederalDistricts();
        }

        $names = static::query()->active()->ordered()->pluck('name')->all();

        return $names !== [] ? $names : Region::fallbackFederalDistricts();
    }

    public function isInUse(): bool
    {
        return Region::query()->where('federal_district', $this->name)->exists();
    }
}

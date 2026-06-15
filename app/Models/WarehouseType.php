<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WarehouseType extends Model
{
    public const APPLIES_MANUFACTURER = 'manufacturer';

    public const APPLIES_DISTRIBUTOR = 'distributor';

    public const APPLIES_BOTH = 'both';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'applies_to',
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

    public function scopeForOwner($query, string $owner)
    {
        return $query->where(function ($q) use ($owner) {
            $q->where('applies_to', self::APPLIES_BOTH)
                ->orWhere('applies_to', $owner);
        });
    }

    /**
     * @return array<string, string>
     */
    public static function labelsMapFor(string $owner): array
    {
        if (! Schema::hasTable('warehouse_types')) {
            return $owner === self::APPLIES_DISTRIBUTOR
                ? DistributorWarehouse::fallbackTypeLabels()
                : Warehouse::fallbackTypeLabels();
        }

        $labels = static::query()->active()->forOwner($owner)->ordered()->pluck('name', 'slug')->all();

        if ($labels !== []) {
            return $labels;
        }

        return $owner === self::APPLIES_DISTRIBUTOR
            ? DistributorWarehouse::fallbackTypeLabels()
            : Warehouse::fallbackTypeLabels();
    }

    /**
     * @return array<string, string>
     */
    public static function appliesToLabels(): array
    {
        return [
            self::APPLIES_MANUFACTURER => 'Производитель',
            self::APPLIES_DISTRIBUTOR => 'Дистрибьютор',
            self::APPLIES_BOTH => 'Оба',
        ];
    }

    public function appliesToLabel(): string
    {
        return self::appliesToLabels()[$this->applies_to] ?? $this->applies_to;
    }

    public function isInUse(): bool
    {
        return Warehouse::query()->where('type', $this->slug)->exists()
            || DistributorWarehouse::query()->where('type', $this->slug)->exists();
    }
}

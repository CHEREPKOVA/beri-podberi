<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class OrderStatus extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_terminal',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_terminal' => 'boolean',
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
     * @return array<string, string>
     */
    public static function labelsMap(): array
    {
        if (! Schema::hasTable('order_statuses')) {
            return PlatformOrder::fallbackStatusLabels();
        }

        $labels = static::query()->active()->ordered()->pluck('name', 'slug')->all();

        return $labels !== [] ? $labels : PlatformOrder::fallbackStatusLabels();
    }

    public function isInUse(): bool
    {
        return PlatformOrder::query()->where('status', $this->slug)->exists();
    }
}

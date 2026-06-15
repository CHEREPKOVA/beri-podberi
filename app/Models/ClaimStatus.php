<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimStatus extends Model
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
        return static::query()->active()->ordered()->pluck('name', 'slug')->all();
    }
}

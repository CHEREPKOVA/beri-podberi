<?php

namespace App\Models;

use Database\Factories\UnitTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @use HasFactory<UnitTypeFactory> */
class UnitType extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'short_name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

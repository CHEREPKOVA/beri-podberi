<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_key',
        'key',
        'label',
        'value',
        'value_type',
        'description',
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

    public function parsedValue(): mixed
    {
        return match ($this->value_type) {
            'boolean' => in_array((string) $this->value, ['1', 'true', 'yes'], true),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            default => $this->value,
        };
    }
}

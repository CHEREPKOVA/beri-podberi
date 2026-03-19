<?php

namespace App\Models;

use Database\Factories\ProductAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<ProductAttributeFactory> */
class ProductAttribute extends Model
{
    use HasFactory;
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_SELECT = 'select';
    public const TYPE_BOOLEAN = 'boolean';

    protected $fillable = [
        'product_category_id',
        'name',
        'slug',
        'type',
        'options',
        'is_filterable',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_TEXT => 'Текст',
            self::TYPE_NUMBER => 'Число',
            self::TYPE_SELECT => 'Список',
            self::TYPE_BOOLEAN => 'Да/Нет',
        ];
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** Атрибуты для категории: глобальные (без категории) + атрибуты этой категории и предков */
    public function scopeForCategory($query, ?int $categoryId)
    {
        if (!$categoryId) {
            return $query->whereNull('product_category_id');
        }
        $category = ProductCategory::find($categoryId);
        $ids = $category ? array_merge($category->ancestorIds(), [$categoryId]) : [];
        return $query->where(function ($q) use ($ids) {
            $q->whereNull('product_category_id');
            if ($ids !== []) {
                $q->orWhereIn('product_category_id', $ids);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }
}

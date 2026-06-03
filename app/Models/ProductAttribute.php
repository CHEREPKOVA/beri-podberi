<?php

namespace App\Models;

use Database\Factories\ProductAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<ProductAttributeFactory> */
class ProductAttribute extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_SELECT = 'select';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_RANGE = 'range';

    public const FILTER_DISPLAY_CHECKBOXES = 'checkboxes';

    public const FILTER_DISPLAY_SELECT = 'select';

    public const FILTER_DISPLAY_RANGE = 'range';

    /** Свободный ввод (по умолчанию для текстового фильтра без списка). */
    public const FILTER_DISPLAY_TEXT = 'text';

    public const FILTER_VALUES_FIXED = 'fixed';

    public const FILTER_VALUES_AUTO = 'auto';

    protected $fillable = [
        'product_category_id',
        'product_id',
        'name',
        'slug',
        'type',
        'options',
        'is_filterable',
        'filter_display_type',
        'filter_values_source',
        'filter_allow_multiple',
        'is_required',
        'sort_order',
        'filter_sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_filterable' => 'boolean',
            'filter_allow_multiple' => 'boolean',
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
            self::TYPE_RANGE => 'Диапазон',
        ];
    }

    public static function filterDisplayLabels(): array
    {
        return [
            self::FILTER_DISPLAY_CHECKBOXES => 'Чекбоксы',
            self::FILTER_DISPLAY_SELECT => 'Выпадающий список',
            self::FILTER_DISPLAY_RANGE => 'Диапазон значений',
            self::FILTER_DISPLAY_TEXT => 'Текстовое поле',
        ];
    }

    public static function filterValuesSourceLabels(): array
    {
        return [
            self::FILTER_VALUES_FIXED => 'Фиксированный список',
            self::FILTER_VALUES_AUTO => 'По значениям товаров категории',
        ];
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** Категории, к которым применяется свойство (пусто = глобальное). */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'product_attribute_category',
            'product_attribute_id',
            'product_category_id'
        )->withTimestamps();
    }

    public function isGlobalCatalogAttribute(): bool
    {
        if ($this->product_id !== null) {
            return false;
        }

        if ($this->relationLoaded('categories')) {
            return $this->categories->isEmpty() && $this->product_category_id === null;
        }

        return ! $this->categories()->exists() && $this->product_category_id === null;
    }

    /**
     * @param  list<int|string>  $categoryIds
     */
    public function syncCatalogCategories(array $categoryIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $categoryIds
        ), static fn (int $id): bool => $id > 0)));

        $this->categories()->sync($ids);
        $this->forceFill(['product_category_id' => $ids[0] ?? null])->save();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeNotProductCustom($query)
    {
        return $query->whereNull('product_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** Порядок в панели фильтров: отдельный от карточки товара. */
    public function scopeOrderedForFilters($query)
    {
        return $query->orderByRaw('COALESCE(filter_sort_order, sort_order)')->orderBy('name');
    }

    /**
     * Атрибуты для категории: глобальные + родители + текущая, за вычетом исключений в подкатегории (ТЗ).
     */
    public function scopeForCategory($query, ?int $categoryId)
    {
        if (! $categoryId) {
            return $query
                ->whereNull('product_id')
                ->whereDoesntHave('categories')
                ->whereNull('product_category_id');
        }

        $category = ProductCategory::find($categoryId);
        $ids = $category ? array_merge($category->ancestorIds(), [$categoryId]) : [];
        $excluded = $category
            ? $category->excludedAttributes()->pluck('product_attributes.id')->all()
            : [];

        return $query
            ->whereNull('product_id')
            ->where(function ($q) use ($ids) {
                $q->where(function ($global) {
                    $global->whereDoesntHave('categories')->whereNull('product_category_id');
                });
                if ($ids !== []) {
                    $q->orWhereHas('categories', fn ($cq) => $cq->whereIn('product_categories.id', $ids))
                        ->orWhereIn('product_category_id', $ids);
                }
            })
            ->when($excluded !== [], fn ($q) => $q->whereNotIn('id', $excluded));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    /**
     * @return list<string>
     */
    public function effectiveFilterOptions(?ProductCategory $category, ?int $manufacturerProfileId = null): array
    {
        if ($this->filter_values_source !== self::FILTER_VALUES_AUTO || $category === null) {
            return $this->options ?? [];
        }

        return self::distinctValueStringsForProductsInCategories(
            $this->id,
            $category->descendant_ids,
            $manufacturerProfileId
        );
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return list<string>
     */
    public static function distinctValueStringsForProductsInCategories(int $attributeId, array $categoryIds, ?int $manufacturerProfileId = null): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $q = ProductAttributeValue::query()
            ->where('product_attribute_id', $attributeId)
            ->whereHas('product', function ($p) use ($categoryIds, $manufacturerProfileId) {
                $p->inAnyCategoryIds($categoryIds)->visibleInCatalog();
                if ($manufacturerProfileId !== null) {
                    $p->where('manufacturer_profile_id', $manufacturerProfileId);
                }
            })
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->where('value', 'not like', '{%');

        return $q->distinct()
            ->orderBy('value')
            ->pluck('value')
            ->values()
            ->all();
    }

    public function resolvedFilterDisplayType(?ProductCategory $category = null, ?int $manufacturerProfileId = null): string
    {
        if ($this->filter_display_type !== null && $this->filter_display_type !== '') {
            return $this->filter_display_type;
        }

        return match ($this->type) {
            self::TYPE_RANGE, self::TYPE_NUMBER => self::FILTER_DISPLAY_RANGE,
            self::TYPE_BOOLEAN => self::FILTER_DISPLAY_SELECT,
            self::TYPE_SELECT => $this->filter_allow_multiple ? self::FILTER_DISPLAY_CHECKBOXES : self::FILTER_DISPLAY_SELECT,
            self::TYPE_TEXT => (function () use ($category, $manufacturerProfileId) {
                $opts = $category
                    ? $this->effectiveFilterOptions($category, $manufacturerProfileId)
                    : [];
                if (count($opts) <= 1) {
                    return self::FILTER_DISPLAY_TEXT;
                }

                return $this->filter_allow_multiple ? self::FILTER_DISPLAY_CHECKBOXES : self::FILTER_DISPLAY_SELECT;
            })(),
        };
    }
}

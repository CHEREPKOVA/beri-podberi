<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** @use HasFactory<ProductFactory> */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'manufacturer_profile_id',
        'category_id',
        'unit_type_id',
        'name',
        'sku',
        'description',
        'video_url',
        'min_order_quantity',
        'base_price',
        'manufacturer_sku',
        'distributor_sku',
        'ean',
        'barcode',
        'expiry_date',
        'storage_conditions',
        'transport_conditions',
        'instruction_url',
        'status',
        'published_at',
        'show_in_catalog',
        'sync_source',
        'synced_at',
        'is_modified',
        'price_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'expiry_date' => 'date',
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
            'price_updated_at' => 'datetime',
            'show_in_catalog' => 'boolean',
            'is_modified' => 'boolean',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Активен',
            self::STATUS_HIDDEN => 'Скрыт',
            self::STATUS_DRAFT => 'Черновик',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-green-100 text-green-800',
            self::STATUS_HIDDEN => 'bg-gray-100 text-gray-800',
            self::STATUS_DRAFT => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** Дополнительные категории (основная — category_id) */
    public function additionalCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product', 'product_id', 'product_category_id')
            ->withTimestamps();
    }

    /** Позиции дистрибьюторов, привязанные к товару производителя. */
    public function distributorProducts(): HasMany
    {
        return $this->hasMany(DistributorProduct::class, 'source_product_id');
    }

    /** Прямо назначенные аналоги товара */
    public function analogs(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_analogs', 'product_id', 'analog_product_id')
            ->withTimestamps();
    }

    /** Обратные связи аналогов (когда текущий товар назначен аналогом другого) */
    public function analogOf(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_analogs', 'analog_product_id', 'product_id')
            ->withTimestamps();
    }

    /**
     * Все ID аналогов (прямые и обратные связи) без дублей.
     *
     * @return array<int, int>
     */
    public function allAnalogIds(): array
    {
        return $this->analogs()
            ->pluck('products.id')
            ->merge($this->analogOf()->pluck('products.id'))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Достаточность характеристик для быстрой оценки аналога.
     */
    public function hasEnoughCharacteristicsForAnalog(?int $minimum = null): bool
    {
        $minimum ??= (int) config('catalog.analogs.min_attributes', 1);

        return $this->attributeValues()->count() >= max(1, $minimum);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first()
            ?? $this->images()->first();
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function regionalPrices(): HasMany
    {
        return $this->hasMany(ProductRegionalPrice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class);
    }

    public function availableRegions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'product_region')->withTimestamps();
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stocks->sum('quantity') - $this->stocks->sum('reserved');
    }

    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->total_stock);
    }

    public function hasStock(): bool
    {
        return $this->available_stock > 0;
    }

    /** Порог (дней) для фильтра «Требуют обновления» — из системных настроек. */
    public static function priceStaleDays(): int
    {
        $days = SystemSetting::getActiveParsed('timings.product_price_stale_days', 30);

        return max(1, (int) $days);
    }

    public static function priceStaleThreshold(): Carbon
    {
        return now()->subDays(self::priceStaleDays());
    }

    public function hasStalePrice(): bool
    {
        if ($this->price_updated_at === null) {
            return true;
        }

        return $this->price_updated_at->lt(self::priceStaleThreshold());
    }

    public function needsUpdate(): bool
    {
        if (! $this->hasStock()) {
            return true;
        }

        return $this->hasStalePrice();
    }

    /** Есть доступный остаток: сумма max(quantity − reserved, 0) по складам > 0. */
    public function scopeWithAvailableStock($query)
    {
        return $query->whereRaw(
            '(SELECT COALESCE(SUM(GREATEST(product_stocks.quantity - product_stocks.reserved, 0)), 0)
              FROM product_stocks
              WHERE product_stocks.product_id = products.id) > 0'
        );
    }

    public function scopeWithoutAvailableStock($query)
    {
        return $query->whereRaw(
            '(SELECT COALESCE(SUM(GREATEST(product_stocks.quantity - product_stocks.reserved, 0)), 0)
              FROM product_stocks
              WHERE product_stocks.product_id = products.id) <= 0'
        );
    }

    public function isSynced(): bool
    {
        return ! empty($this->sync_source);
    }

    public function canBePublished(): bool
    {
        return ! empty($this->name)
            && ! empty($this->base_price)
            && $this->category_id
            && $this->hasStock();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('show_in_catalog', true);
    }

    /** Опубликован в каталоге и привязан к основной категории. */
    public function scopeVisibleInCatalog($query)
    {
        return $query->published()->whereNotNull('category_id');
    }

    public function isVisibleInCatalog(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->show_in_catalog
            && $this->category_id !== null;
    }

    public function scopeNeedsUpdate($query)
    {
        $threshold = self::priceStaleThreshold();

        return $query->where(function ($q) use ($threshold) {
            $q->withoutAvailableStock()
                ->orWhere(function ($priceQuery) use ($threshold) {
                    $priceQuery->whereNull('price_updated_at')
                        ->orWhere('price_updated_at', '<', $threshold);
                });
        });
    }

    public function scopeForManufacturer($query, $profileId)
    {
        return $query->where('manufacturer_profile_id', $profileId);
    }

    /**
     * Совместимые товары: из той же основной/доп. категории либо с пересечением доп.категорий.
     */
    public function scopeCompatibleWithProduct($query, Product $product)
    {
        $product->loadMissing('additionalCategories:id');

        $baseCategoryIds = collect([$product->category_id])
            ->merge($product->additionalCategories->pluck('id'))
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($baseCategoryIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $ids = $baseCategoryIds->all();

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('category_id', $ids)
                ->orWhereHas('additionalCategories', fn ($aq) => $aq->whereIn('product_categories.id', $ids));
        });
    }

    /**
     * Фильтр по значениям атрибутов:
     * - attribute_id => одно значение или список (чекбоксы/списки),
     * - attribute_id => ['min'=>?, 'max'=>?] для числа или типа «диапазон» у товара.
     */
    public function scopeWithAttributeFilters($query, array $filters)
    {
        foreach ($filters as $attributeId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $attributeId = (int) $attributeId;
            if ($attributeId <= 0) {
                continue;
            }

            $attr = ProductAttribute::query()->find($attributeId);
            if (! $attr instanceof ProductAttribute) {
                continue;
            }

            if (is_array($value) && self::attributeFilterPayloadIsNumericRange($value)) {
                $fmin = isset($value['min']) && $value['min'] !== '' ? (float) $value['min'] : null;
                $fmax = isset($value['max']) && $value['max'] !== '' ? (float) $value['max'] : null;
                if ($fmin === null && $fmax === null) {
                    continue;
                }
                self::applyAttributeNumericRangeConstraint($query, $attr, $fmin, $fmax);

                continue;
            }

            $scalarValues = is_array($value) ? $value : [$value];
            $scalarValues = array_values(array_filter(array_map(static fn ($v) => is_scalar($v) ? (string) $v : null, $scalarValues), static fn ($v) => $v !== null && $v !== ''));
            if ($scalarValues === []) {
                continue;
            }

            $query->whereHas('attributeValues', function ($q) use ($attributeId, $scalarValues) {
                $q->where('product_attribute_id', $attributeId)->whereIn('value', $scalarValues);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected static function attributeFilterPayloadIsNumericRange(array $value): bool
    {
        return array_key_exists('min', $value) || array_key_exists('max', $value);
    }

    protected static function applyAttributeNumericRangeConstraint($query, ProductAttribute $attr, ?float $fmin, ?float $fmax): void
    {
        if ($attr->type === ProductAttribute::TYPE_RANGE) {
            $driver = DB::connection()->getDriverName();
            $query->whereHas('attributeValues', function ($q) use ($attr, $fmin, $fmax, $driver) {
                $q->where('product_attribute_id', $attr->id);
                if ($driver === 'sqlite') {
                    if ($fmin !== null) {
                        $q->whereRaw('CAST(json_extract(product_attribute_values.value, \'$.max\') AS REAL) >= ?', [$fmin]);
                    }
                    if ($fmax !== null) {
                        $q->whereRaw('CAST(json_extract(product_attribute_values.value, \'$.min\') AS REAL) <= ?', [$fmax]);
                    }
                } elseif ($driver === 'mysql') {
                    if ($fmin !== null) {
                        $q->whereRaw(
                            'CAST(JSON_UNQUOTE(JSON_EXTRACT(product_attribute_values.value, \'$.max\')) AS DECIMAL(40,15)) >= ?',
                            [$fmin]
                        );
                    }
                    if ($fmax !== null) {
                        $q->whereRaw(
                            'CAST(JSON_UNQUOTE(JSON_EXTRACT(product_attribute_values.value, \'$.min\')) AS DECIMAL(40,15)) <= ?',
                            [$fmax]
                        );
                    }
                } else {
                    if ($fmin !== null) {
                        $q->whereRaw('CAST(json_extract(product_attribute_values.value, \'$.max\') AS REAL) >= ?', [$fmin]);
                    }
                    if ($fmax !== null) {
                        $q->whereRaw('CAST(json_extract(product_attribute_values.value, \'$.min\') AS REAL) <= ?', [$fmax]);
                    }
                }
            });

            return;
        }

        $query->whereHas('attributeValues', function ($q) use ($attr, $fmin, $fmax) {
            $q->where('product_attribute_id', $attr->id);
            if ($fmin !== null) {
                $q->whereRaw('CAST(product_attribute_values.value AS REAL) >= ?', [$fmin]);
            }
            if ($fmax !== null) {
                $q->whereRaw('CAST(product_attribute_values.value AS REAL) <= ?', [$fmax]);
            }
        });
    }

    /**
     * Товар в одной из категорий ветки: основная или дополнительная (ТЗ).
     *
     * @param  list<int>  $categoryIds
     */
    public function scopeInAnyCategoryIds($query, array $categoryIds)
    {
        if ($categoryIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds)
                ->orWhereHas(
                    'additionalCategories',
                    fn ($aq) => $aq->whereIn('product_categories.id', $categoryIds)
                );
        });
    }

    /** Товары в данной категории или в любой из её подкатегорий (основная или доп.). */
    public function scopeInCategory($query, $categoryId)
    {
        if (! $categoryId) {
            return $query;
        }
        $category = ProductCategory::find($categoryId);
        if (! $category) {
            return $query;
        }

        return $query->inAnyCategoryIds($category->descendant_ids);
    }

    /**
     * Конечная компания: товар доступен через активного дистрибьютора в регионе (ТЗ).
     */
    public function scopeVisibleViaDistributorsInRegion($query, ?int $regionId)
    {
        if ($regionId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('distributorProducts', function ($dq) use ($regionId) {
            $dq->where('status', DistributorProduct::STATUS_ACTIVE)
                ->whereColumn('distributor_products.manufacturer_profile_id', 'products.manufacturer_profile_id')
                ->whereHas('profile', fn ($profile) => $profile->inRegion($regionId))
                ->whereHas('profile.manufacturerPartnerships', function ($partnership) {
                    $partnership->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
                        ->whereColumn(
                            'manufacturer_distributor_partnerships.manufacturer_profile_id',
                            'products.manufacturer_profile_id'
                        );
                });
        });
    }

    /** Значение характеристики товара по slug (для логистики и карточки). */
    public function attributeValueBySlug(string $slug): ?string
    {
        $this->loadMissing('attributeValues.attribute');

        foreach ($this->attributeValues as $value) {
            if ($value->attribute && $value->attribute->slug === $slug && $value->value !== null && $value->value !== '') {
                return (string) $value->value;
            }
        }

        return null;
    }

    public function scopeSearch($query, ?string $search)
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }

        $compact = preg_replace('/\s+/u', '', $search);

        return $query->where(function ($q) use ($search, $compact) {
            $q->where('name', 'like', "%{$search}%");

            foreach (['sku', 'manufacturer_sku', 'distributor_sku', 'ean', 'barcode'] as $field) {
                $q->orWhere($field, 'like', "%{$search}%")
                    ->orWhereRaw(
                        "REPLACE(REPLACE(COALESCE({$field}, ''), ' ', ''), CHAR(9), '') LIKE ?",
                        ["%{$compact}%"]
                    );
            }

            $q->orWhereHas('attributeValues', function ($av) use ($search) {
                $av->where('value', 'like', "%{$search}%");
            });
        });
    }

    /** Значения атрибутов, актуальных для категории товара (карточка и каталог). */
    public function attributeValuesVisibleInCategory(?int $categoryId = null): Collection
    {
        $this->loadMissing(['attributeValues.attribute']);
        $categoryId ??= $this->category_id;
        if (! $categoryId) {
            return collect();
        }

        $allowedIds = ProductAttribute::query()
            ->active()
            ->forCategory($categoryId)
            ->pluck('id');

        return $this->attributeValues
            ->filter(fn ($value) => $value->attribute && $allowedIds->contains($value->product_attribute_id))
            ->sortBy(fn ($value) => $value->attribute->sort_order ?? 0)
            ->values();
    }

    /**
     * Товары, доступные в указанном регионе (по региональной привязке ассортимента).
     * Если у товара не заданы регионы (availableRegions пусты) — доступен везде.
     */
    public function scopeForRegion($query, ?int $regionId)
    {
        if ($regionId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($regionId) {
            $q->whereDoesntHave('availableRegions') // нет ограничений = все регионы
                ->orWhereHas('availableRegions', fn ($r) => $r->where('regions.id', $regionId));
        });
    }

    /**
     * Товары, доступные конечной компании в регионе: поставщик (производитель) работает
     * в этом регионе и товар доступен в регионе (forRegion).
     */
    public function scopeAvailableInRegion($query, ?int $regionId)
    {
        if ($regionId === null) {
            return $query;
        }

        return $query->forRegion($regionId)->whereHas('manufacturerProfile', function ($q) use ($regionId) {
            $q->whereHas('regions', fn ($r) => $r->where('regions.id', $regionId));
        });
    }

    /**
     * Цена для региона: региональная если задана, иначе базовая.
     */
    public function getPriceForRegion(?int $regionId): string
    {
        if ($regionId !== null) {
            $rp = $this->regionalPrices()->where('region_id', $regionId)->first();
            if ($rp !== null && $rp->price !== null) {
                return (string) $rp->price;
            }
        }

        return (string) ($this->base_price ?? '0');
    }

    /**
     * Остатки только по складам, относящимся к указанному региону.
     */
    public function getAvailableStockInRegion(?int $regionId): int
    {
        if ($regionId === null) {
            return $this->available_stock;
        }
        $quantity = $this->stocks()
            ->whereHas('warehouse', fn ($q) => $q->where('region_id', $regionId))
            ->get()
            ->sum(fn ($s) => max(0, $s->quantity - $s->reserved));

        return (int) $quantity;
    }

    /**
     * Видимые остатки по складам с учетом активности склада и региона пользователя.
     *
     * @return Collection<int, ProductStock>
     */
    public function visibleStocksForRegion(?int $regionId): Collection
    {
        $stocks = $this->relationLoaded('stocks')
            ? $this->stocks
            : $this->stocks()->with('warehouse.region')->get();

        return $stocks
            ->filter(function (ProductStock $stock) use ($regionId): bool {
                $warehouse = $stock->warehouse;
                if (! $warehouse || ! $warehouse->is_active) {
                    return false;
                }

                if ($regionId !== null && (int) $warehouse->region_id !== $regionId) {
                    return false;
                }

                return true;
            })
            ->sortByDesc('stock_updated_at')
            ->values();
    }
}

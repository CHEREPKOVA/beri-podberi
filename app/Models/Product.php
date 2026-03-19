<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function needsUpdate(): bool
    {
        if ($this->available_stock <= 0) {
            return true;
        }

        if ($this->price_updated_at && $this->price_updated_at->diffInDays(now()) > 30) {
            return true;
        }

        return false;
    }

    public function isSynced(): bool
    {
        return !empty($this->sync_source);
    }

    public function canBePublished(): bool
    {
        return !empty($this->name)
            && !empty($this->base_price)
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

    public function scopeNeedsUpdate($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('stocks', function ($sq) {
                $sq->where('quantity', '>', 0);
            })->orWhere('price_updated_at', '<', now()->subDays(30));
        });
    }

    public function scopeForManufacturer($query, $profileId)
    {
        return $query->where('manufacturer_profile_id', $profileId);
    }

    /** Фильтр по значениям атрибутов (массив [attribute_id => value] или [attribute_id => [values]]) */
    public function scopeWithAttributeFilters($query, array $filters)
    {
        foreach ($filters as $attributeId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $values = is_array($value) ? $value : [$value];
            $query->whereHas('attributeValues', function ($q) use ($attributeId, $values) {
                $q->where('product_attribute_id', $attributeId)->whereIn('value', $values);
            });
        }
        return $query;
    }

    /** Товары в данной категории или в любой из её подкатегорий */
    public function scopeInCategory($query, $categoryId)
    {
        if (!$categoryId) {
            return $query;
        }
        $category = ProductCategory::find($categoryId);
        if (!$category) {
            return $query;
        }
        $ids = $category->descendant_ids;

        return $query->whereIn('category_id', $ids);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('manufacturer_sku', 'like', "%{$search}%");
        });
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributorProduct extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_ARCHIVE = 'archive';

    public const SYNC_MANUAL = 'manual';

    public const SYNC_CSV = 'csv';

    public const SYNC_YML = 'yml';

    public const SYNC_1C = '1c';

    public const SYNC_MANUFACTURER = 'manufacturer';

    protected $fillable = [
        'distributor_profile_id',
        'source_product_id',
        'manufacturer_profile_id',
        'product_category_id',
        'unit_type_id',
        'name',
        'internal_sku',
        'manufacturer_sku',
        'brand',
        'barcode',
        'short_description',
        'description',
        'country_of_origin',
        'pack_quantity',
        'min_order_quantity',
        'purchase_price',
        'retail_price',
        'price_updated_at',
        'status',
        'sync_source',
        'synced_at',
        'manufacturer_archived',
        'managed_by_1c',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'price_updated_at' => 'datetime',
            'synced_at' => 'datetime',
            'manufacturer_archived' => 'boolean',
            'managed_by_1c' => 'boolean',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Активен',
            self::STATUS_HIDDEN => 'Скрыт',
            self::STATUS_ARCHIVE => 'Архив',
        ];
    }

    public static function syncSourceLabels(): array
    {
        return [
            self::SYNC_MANUAL => 'Ручное добавление',
            self::SYNC_CSV => 'CSV',
            self::SYNC_YML => 'YML',
            self::SYNC_1C => '1С',
            self::SYNC_MANUFACTURER => 'Каталог производителя',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function syncSourceLabel(): string
    {
        return self::syncSourceLabels()[$this->sync_source] ?? $this->sync_source;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::STATUS_HIDDEN => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::STATUS_ARCHIVE => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    public function isSyncedFrom1c(): bool
    {
        return $this->sync_source === self::SYNC_1C || $this->managed_by_1c;
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class, 'distributor_profile_id');
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(DistributorProductStock::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(DistributorProductPriceHistory::class)->latest();
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(DistributorProductChangeLog::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DistributorProductDocument::class);
    }

    public function regionalPrices(): HasMany
    {
        return $this->hasMany(DistributorProductRegionalPrice::class);
    }

    public function retailPriceForRegion(?int $regionId): ?float
    {
        if ($regionId !== null) {
            $regional = $this->relationLoaded('regionalPrices')
                ? $this->regionalPrices->firstWhere('region_id', $regionId)
                : $this->regionalPrices()->where('region_id', $regionId)->first();

            if ($regional !== null && $regional->price !== null) {
                return (float) $regional->price;
            }
        }

        return $this->retail_price !== null ? (float) $this->retail_price : null;
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->stocks->sum(fn (DistributorProductStock $s) => max(0, $s->quantity - $s->reserved));
    }

    public function hasStock(): bool
    {
        return $this->total_stock > 0;
    }

    public function manufacturerName(): ?string
    {
        return $this->brand
            ?: $this->manufacturerProfile?->displayName()
            ?: $this->sourceProduct?->manufacturerProfile?->displayName();
    }

    public function primaryImageUrl(): ?string
    {
        $source = $this->sourceProduct;
        if ($source) {
            $img = $source->primaryImage();

            return $img?->url;
        }

        return null;
    }

    public function scopeForDistributor($query, int $profileId)
    {
        return $query->where('distributor_profile_id', $profileId);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('internal_sku', 'like', "%{$search}%")
                ->orWhere('manufacturer_sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhereHas('manufacturerProfile', fn ($mq) => $mq->where('full_name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%"));
        });
    }

    public function scopeWithStockFilter($query, ?string $hasStock)
    {
        if ($hasStock === 'yes') {
            return $query->whereHas('stocks', fn ($q) => $q->whereRaw('quantity > reserved'));
        }
        if ($hasStock === 'no') {
            return $query->whereDoesntHave('stocks', fn ($q) => $q->whereRaw('quantity > reserved'));
        }

        return $query;
    }
}

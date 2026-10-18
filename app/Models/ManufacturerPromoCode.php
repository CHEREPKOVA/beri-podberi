<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ManufacturerPromoCode extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'manufacturer_profile_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'status',
        'starts_at',
        'ends_at',
        'max_uses',
        'per_user_limit',
        'min_order_amount',
        'apply_to_all_products',
        'category_ids',
        'product_ids',
        'region_ids',
        'company_ids',
        'uses_count',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'per_user_limit' => 'integer',
            'uses_count' => 'integer',
            'apply_to_all_products' => 'boolean',
            'category_ids' => 'array',
            'product_ids' => 'array',
            'region_ids' => 'array',
            'company_ids' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ManufacturerPromoCodeUsage::class, 'promo_code_id');
    }

    public function typeLabel(): string
    {
        return (string) (config('manufacturer_discounts.promo_discount_types.'.$this->discount_type) ?? $this->discount_type);
    }

    public function sizeLabel(): string
    {
        if ($this->discount_type === self::TYPE_FIXED) {
            return number_format((float) $this->discount_value, 2, ',', ' ').' ₽';
        }

        return rtrim(rtrim(number_format((float) $this->discount_value, 2, ',', ' '), '0'), ',').' %';
    }

    public function periodLabel(): string
    {
        if ($this->starts_at === null && $this->ends_at === null) {
            return 'Бессрочно';
        }

        $from = $this->starts_at?->format('d.m.Y') ?? '—';
        $to = $this->ends_at?->format('d.m.Y') ?? 'бессрочно';

        return $from.' — '.$to;
    }

    public function effectiveStatus(?Carbon $at = null): string
    {
        if ($this->status === self::STATUS_INACTIVE) {
            return self::STATUS_INACTIVE;
        }

        $at = ($at ?? now())->startOfDay();
        if ($this->ends_at !== null && $this->ends_at->lt($at)) {
            return self::STATUS_EXPIRED;
        }

        if ($this->max_uses !== null && (int) $this->uses_count >= (int) $this->max_uses) {
            return self::STATUS_USED;
        }

        return self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return (string) (config('manufacturer_discounts.promo_statuses.'.$this->effectiveStatus()) ?? $this->status);
    }

    public function isLocked(): bool
    {
        return (int) $this->uses_count > 0;
    }

    public function isCurrentlyApplicable(?Carbon $at = null): bool
    {
        if ($this->effectiveStatus($at) !== self::STATUS_ACTIVE) {
            return false;
        }

        $at = ($at ?? now())->startOfDay();
        if ($this->starts_at !== null && $this->starts_at->gt($at)) {
            return false;
        }

        return true;
    }

    public function isEffectivelyActive(?Carbon $at = null): bool
    {
        return $this->isCurrentlyApplicable($at);
    }

    /**
     * @return list<int>
     */
    public function intList(?array $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $values), fn (int $id): bool => $id > 0)));
    }

    public function scopeForManufacturer(Builder $query, int $manufacturerProfileId): Builder
    {
        return $query->where('manufacturer_profile_id', $manufacturerProfileId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('code', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        });
    }
}

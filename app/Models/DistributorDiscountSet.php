<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DistributorDiscountSet extends Model
{
    public const TYPE_CATEGORY = 'category';

    public const TYPE_BRAND = 'brand';

    public const TYPE_PRODUCTS = 'products';

    public const TYPE_PERSONAL = 'personal';

    public const TYPE_MASS = 'mass';

    public const VALUE_PERCENT = 'percent';

    public const VALUE_FIXED = 'fixed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SCHEDULED = 'scheduled';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_SELECTED = 'selected';

    public const AUDIENCE_REGION = 'region';

    public const AUDIENCE_COMPANY_TYPES = 'company_types';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CSV = 'csv';

    public const SOURCE_1C = '1c';

    protected $fillable = [
        'distributor_profile_id',
        'name',
        'description',
        'type',
        'value_kind',
        'status',
        'discount_percent',
        'discount_amount',
        'min_order_amount',
        'min_quantity',
        'warehouse_ids',
        'do_not_stack',
        'priority',
        'source',
        'apply_to_all_products',
        'category_ids',
        'brands',
        'product_ids',
        'audience_type',
        'company_ids',
        'region_ids',
        'company_types',
        'starts_at',
        'ends_at',
        'is_indefinite',
        'auto_deactivate',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'min_quantity' => 'integer',
            'warehouse_ids' => 'array',
            'do_not_stack' => 'boolean',
            'priority' => 'integer',
            'apply_to_all_products' => 'boolean',
            'category_ids' => 'array',
            'brands' => 'array',
            'product_ids' => 'array',
            'company_ids' => 'array',
            'region_ids' => 'array',
            'company_types' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_indefinite' => 'boolean',
            'auto_deactivate' => 'boolean',
        ];
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DistributorDiscountSetLog::class, 'discount_set_id')->orderByDesc('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(PlatformOrderItem::class, 'distributor_discount_set_id');
    }

    public function typeLabel(): string
    {
        return (string) (config('distributor_discounts.types.'.$this->type) ?? $this->type);
    }

    public function statusLabel(): string
    {
        return (string) (config('distributor_discounts.statuses.'.$this->effectiveStatus()) ?? $this->status);
    }

    public function audienceLabel(): string
    {
        return (string) (config('distributor_discounts.audience_types.'.$this->audience_type) ?? $this->audience_type);
    }

    public function sourceLabel(): string
    {
        return (string) (config('distributor_discounts.sources.'.$this->source) ?? $this->source);
    }

    public function effectiveStatus(?Carbon $at = null): string
    {
        if ($this->status === self::STATUS_INACTIVE) {
            return self::STATUS_INACTIVE;
        }

        $at = ($at ?? now())->startOfDay();
        if ($this->ends_at !== null && ! $this->is_indefinite && $this->ends_at->lt($at)) {
            return self::STATUS_COMPLETED;
        }

        if ($this->starts_at !== null && $this->starts_at->gt($at)) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_ACTIVE;
    }

    public function isCurrentlyApplicable(?Carbon $at = null): bool
    {
        return $this->effectiveStatus($at) === self::STATUS_ACTIVE;
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

    /**
     * @return list<string>
     */
    public function stringList(?array $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => $value !== '')));
    }

    public function sizeLabel(): string
    {
        if ($this->value_kind === self::VALUE_FIXED || ((float) ($this->discount_amount ?? 0) > 0 && (float) ($this->discount_percent ?? 0) <= 0)) {
            return $this->discount_amount !== null
                ? number_format((float) $this->discount_amount, 2, ',', ' ').' ₽'
                : '—';
        }

        return $this->discount_percent !== null
            ? rtrim(rtrim(number_format((float) $this->discount_percent, 2, ',', ' '), '0'), ',').' %'
            : '—';
    }

    public function periodLabel(): string
    {
        if ($this->is_indefinite || ($this->starts_at === null && $this->ends_at === null)) {
            $from = $this->starts_at?->format('d.m.Y');

            return $from ? 'с '.$from.' · бессрочно' : 'Бессрочно';
        }

        $from = $this->starts_at?->format('d.m.Y') ?? '—';
        $to = $this->ends_at?->format('d.m.Y') ?? '—';

        return $from.' — '.$to;
    }

    public function productScopeCount(): int
    {
        return count($this->intList($this->product_ids));
    }

    public function clientCount(): int
    {
        if (in_array($this->type, [self::TYPE_PERSONAL, self::TYPE_MASS], true)) {
            return count($this->intList($this->company_ids));
        }

        return 0;
    }

    public function appliesToAllClients(): bool
    {
        return ! in_array($this->type, [self::TYPE_PERSONAL, self::TYPE_MASS], true)
            || $this->audience_type === self::AUDIENCE_ALL;
    }

    public function scopeForDistributor(Builder $query, int $distributorProfileId): Builder
    {
        return $query->where('distributor_profile_id', $distributorProfileId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        });
    }
}

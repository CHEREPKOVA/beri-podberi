<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ManufacturerDiscountSet extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_VOLUME_TIER = 'volume_tier';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_REGION = 'region';

    public const TYPE_PROMO = 'promo';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SCHEDULED = 'scheduled';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_SELECTED = 'selected';

    public const AUDIENCE_REGION = 'region';

    public const AUDIENCE_EXCLUSIVE = 'exclusive';

    public const AUDIENCE_COMPANY_TYPES = 'company_types';

    protected $fillable = [
        'manufacturer_profile_id',
        'name',
        'description',
        'type',
        'status',
        'discount_percent',
        'discount_amount',
        'min_order_amount',
        'min_quantity',
        'require_all_conditions',
        'apply_to_all_products',
        'apply_only_active_products',
        'only_new_distributors',
        'audience_type',
        'category_ids',
        'excluded_category_ids',
        'product_ids',
        'region_ids',
        'audience_region_ids',
        'distributor_ids',
        'company_types',
        'tiers',
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
            'require_all_conditions' => 'boolean',
            'apply_to_all_products' => 'boolean',
            'apply_only_active_products' => 'boolean',
            'only_new_distributors' => 'boolean',
            'category_ids' => 'array',
            'excluded_category_ids' => 'array',
            'product_ids' => 'array',
            'region_ids' => 'array',
            'audience_region_ids' => 'array',
            'distributor_ids' => 'array',
            'company_types' => 'array',
            'tiers' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_indefinite' => 'boolean',
            'auto_deactivate' => 'boolean',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ManufacturerDiscountSetLog::class, 'discount_set_id')->orderByDesc('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(PlatformOrderItem::class, 'discount_set_id');
    }

    public function typeLabel(): string
    {
        return (string) (config('manufacturer_discounts.types.'.$this->type) ?? $this->type);
    }

    public function statusLabel(): string
    {
        return (string) (config('manufacturer_discounts.statuses.'.$this->effectiveStatus()) ?? $this->status);
    }

    public function audienceLabel(): string
    {
        return (string) (config('manufacturer_discounts.audience_types.'.$this->audience_type) ?? $this->audience_type);
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
        if ($this->effectiveStatus($at) !== self::STATUS_ACTIVE) {
            return false;
        }

        $at = ($at ?? now())->startOfDay();
        if ($this->starts_at !== null && $this->starts_at->gt($at)) {
            return false;
        }

        if ($this->ends_at !== null && ! $this->is_indefinite && $this->ends_at->lt($at)) {
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

    /**
     * @return list<array{min_amount:?float,min_quantity:?int,percent:?float,amount:?float}>
     */
    public function normalizedTiers(): array
    {
        $tiers = is_array($this->tiers) ? $this->tiers : [];
        $normalized = [];
        foreach ($tiers as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            $normalized[] = [
                'min_amount' => isset($tier['min_amount']) && $tier['min_amount'] !== '' ? (float) $tier['min_amount'] : null,
                'min_quantity' => isset($tier['min_quantity']) && $tier['min_quantity'] !== '' ? (int) $tier['min_quantity'] : null,
                'percent' => isset($tier['percent']) && $tier['percent'] !== '' ? (float) $tier['percent'] : null,
                'amount' => isset($tier['amount']) && $tier['amount'] !== '' ? (float) $tier['amount'] : null,
            ];
        }

        usort($normalized, function (array $a, array $b): int {
            $aAmount = $a['min_amount'] ?? 0;
            $bAmount = $b['min_amount'] ?? 0;
            if ($aAmount === $bAmount) {
                return ($a['min_quantity'] ?? 0) <=> ($b['min_quantity'] ?? 0);
            }

            return $aAmount <=> $bAmount;
        });

        return $normalized;
    }

    public function sizeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_FIXED => $this->discount_amount !== null
                ? number_format((float) $this->discount_amount, 2, ',', ' ').' ₽'
                : '—',
            self::TYPE_VOLUME_TIER => $this->tierSummary(),
            default => $this->discount_percent !== null
                ? rtrim(rtrim(number_format((float) $this->discount_percent, 2, ',', ' '), '0'), ',').' %'
                : '—',
        };
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

    public function appliesLabel(): string
    {
        $parts = [];
        if ($this->apply_to_all_products) {
            $parts[] = 'Все товары';
        } else {
            $categories = count($this->intList($this->category_ids));
            $products = count($this->intList($this->product_ids));
            if ($categories > 0) {
                $parts[] = 'Категории: '.$categories;
            }
            if ($products > 0) {
                $parts[] = 'Товары: '.$products;
            }
        }

        $regions = count($this->intList($this->region_ids));
        if ($regions > 0) {
            $parts[] = 'Регионы: '.$regions;
        }

        $parts[] = $this->audienceLabel();

        return implode(' · ', $parts);
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
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->orWhere('type', 'like', '%'.$term.'%');
        });
    }

    private function tierSummary(): string
    {
        $tiers = $this->normalizedTiers();
        if ($tiers === []) {
            return 'Градации не заданы';
        }

        $parts = [];
        foreach (array_slice($tiers, 0, 3) as $tier) {
            $threshold = $tier['min_amount'] !== null
                ? number_format($tier['min_amount'], 0, ',', ' ').' ₽'
                : (($tier['min_quantity'] ?? 0).' шт.');
            $value = $tier['percent'] !== null
                ? rtrim(rtrim(number_format($tier['percent'], 2, ',', ' '), '0'), ',').'%'
                : number_format((float) ($tier['amount'] ?? 0), 2, ',', ' ').' ₽';
            $parts[] = 'от '.$threshold.' → '.$value;
        }

        if (count($tiers) > 3) {
            $parts[] = '…';
        }

        return implode(', ', $parts);
    }
}

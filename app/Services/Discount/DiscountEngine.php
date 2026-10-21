<?php

namespace App\Services\Discount;

use App\Models\DistributorProfile;
use App\Models\ManufacturerDiscountSet;
use App\Models\ManufacturerDistributorExclusiveRegion;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerPromoCode;
use App\Models\PlatformOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DiscountEngine
{
    /** @var array<int, Collection<int, ManufacturerDiscountSet>> */
    private array $setsCache = [];

    /** @var array<int, array<int, list<int>>> */
    private array $categoryDescendantsCache = [];

    /**
     * @param  list<array{
     *     product_id:int,
     *     category_id:?int,
     *     quantity:int,
     *     list_unit_price:float,
     *     status?:?string
     * }>  $items
     * @return array{
     *     items: list<array<string, mixed>>,
     *     discount_amount: float,
     *     discount_label: ?string,
     *     discount_set_id: ?int,
     *     promo_code_id: ?int,
     *     promo_code: ?string,
     *     explanations: list<string>
     * }
     */
    public function quote(
        int $manufacturerProfileId,
        DistributorProfile $distributor,
        array $items,
        ?int $regionId = null,
        ?string $promoCode = null,
        ?User $user = null,
    ): array {
        $sets = $this->activeSets($manufacturerProfileId)
            ->filter(fn (ManufacturerDiscountSet $set): bool => $this->matchesAudience($set, $distributor, $regionId))
            ->values();

        $quoted = [];
        foreach ($items as $index => $item) {
            $quoted[$index] = $this->quoteItem($sets, $item, $items, $regionId);
        }

        $promo = $this->resolvePromo($manufacturerProfileId, $promoCode, $distributor, $regionId, $items, $user);
        $explanations = [];
        $promoAmount = 0.0;

        if ($promo !== null) {
            foreach ($quoted as $index => $row) {
                if (! $this->promoAppliesToItem($promo, $items[$index])) {
                    continue;
                }

                $list = (float) $row['list_unit_price'];
                $qty = (int) $row['quantity'];
                $current = (float) $row['unit_price'];
                $promoUnit = $this->promoUnitDiscount($promo, $list);
                $newUnit = max(0, round($current - $promoUnit, 2));
                if ($newUnit >= $current) {
                    continue;
                }

                $lineDiscount = round(($current - $newUnit) * $qty, 2);
                $promoAmount += $lineDiscount;
                $quoted[$index]['unit_price'] = $newUnit;
                $quoted[$index]['line_total'] = round($newUnit * $qty, 2);
                $quoted[$index]['discount_amount'] = round(($list - $newUnit) * $qty, 2);
                $quoted[$index]['promo_code_id'] = $promo->id;
                $quoted[$index]['has_discount'] = $list > $newUnit + 0.009;
            }

            $explanations[] = 'Промокод '.$promo->code.' применён: '.$promo->sizeLabel().'.';
        }

        $setNames = collect($quoted)
            ->pluck('discount_label')
            ->filter()
            ->unique()
            ->values();

        $primarySetId = collect($quoted)->pluck('discount_set_id')->filter()->countBy()->sortDesc()->keys()->first();

        $totalDiscount = round(array_sum(array_map(fn (array $row): float => (float) ($row['discount_amount'] ?? 0), $quoted)), 2);

        foreach ($quoted as $row) {
            if (! empty($row['explanation'])) {
                $explanations[] = $row['explanation'];
            }
        }

        return [
            'items' => array_values($quoted),
            'discount_amount' => $totalDiscount,
            'discount_label' => $setNames->implode(', ') ?: null,
            'discount_set_id' => $primarySetId ? (int) $primarySetId : null,
            'promo_code_id' => $promo?->id,
            'promo_code' => $promo?->code,
            'promo_discount_amount' => round($promoAmount, 2),
            'explanations' => array_values(array_unique($explanations)),
            'auto_discount_amount' => max(0, round($totalDiscount - $promoAmount, 2)),
        ];
    }

    /**
     * @return array{
     *     applies: bool,
     *     unit_price: float,
     *     list_unit_price: float,
     *     discount_amount: float,
     *     explanation: string
     * }
     */
    public function preview(
        ManufacturerDiscountSet $set,
        DistributorProfile $distributor,
        Product $product,
        int $quantity,
        ?int $regionId = null,
    ): array {
        $list = (float) $product->getPriceForRegion($regionId);
        $quantity = max(1, $quantity);
        $item = [
            'product_id' => (int) $product->id,
            'category_id' => $product->category_id ? (int) $product->category_id : null,
            'quantity' => $quantity,
            'list_unit_price' => $list,
            'status' => $product->status,
        ];

        $reasons = [];
        if (! $this->matchesAudience($set, $distributor, $regionId, $reasons)) {
            return $this->previewFail($list, $quantity, 'Набор не применяется к выбранному дистрибьютору. '.implode(' ', $reasons));
        }

        if (! $this->setCoversItem($set, $item, $regionId, $reasons)) {
            return $this->previewFail($list, $quantity, 'Набор не покрывает выбранный товар. '.implode(' ', $reasons));
        }

        if (! $this->conditionsMet($set, [$item], $reasons)) {
            return $this->previewFail($list, $quantity, implode(' ', $reasons) ?: 'Условия набора не выполнены.');
        }

        $unitDiscount = $this->unitDiscountForSet($set, $item, [$item]);
        $unitPrice = max(0, round($list - $unitDiscount, 2));
        $lineDiscount = round(($list - $unitPrice) * $quantity, 2);
        $explanation = $this->buildExplanation($set, $item, [$item], $list, $unitPrice);

        return [
            'applies' => $lineDiscount > 0,
            'unit_price' => $unitPrice,
            'list_unit_price' => $list,
            'discount_amount' => $lineDiscount,
            'explanation' => $explanation,
        ];
    }

    public function quoteSingleProduct(
        int $manufacturerProfileId,
        DistributorProfile $distributor,
        Product $product,
        int $quantity,
        ?int $regionId = null,
    ): array {
        $list = (float) $product->getPriceForRegion($regionId);
        $result = $this->quote($manufacturerProfileId, $distributor, [[
            'product_id' => (int) $product->id,
            'category_id' => $product->category_id ? (int) $product->category_id : null,
            'quantity' => max(1, $quantity),
            'list_unit_price' => $list,
            'status' => $product->status,
        ]], $regionId);

        $row = $result['items'][0] ?? [
            'unit_price' => $list,
            'list_unit_price' => $list,
            'discount_amount' => 0,
            'discount_label' => null,
            'has_discount' => false,
        ];

        return $row;
    }

    /**
     * @param  Collection<int, ManufacturerDiscountSet>  $sets
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $allItems
     * @return array<string, mixed>
     */
    private function quoteItem(Collection $sets, array $item, array $allItems, ?int $regionId): array
    {
        $list = (float) $item['list_unit_price'];
        $qty = max(1, (int) $item['quantity']);
        $candidates = [];

        foreach ($sets as $set) {
            if (! $this->setCoversItem($set, $item, $regionId)) {
                continue;
            }
            $eligible = array_values(array_filter(
                $allItems,
                fn (array $other): bool => $this->setCoversItem($set, $other, $regionId)
            ));
            if (! $this->conditionsMet($set, $eligible)) {
                continue;
            }

            $unitDiscount = $this->unitDiscountForSet($set, $item, $eligible);
            if ($unitDiscount <= 0) {
                continue;
            }

            $candidates[] = [
                'set' => $set,
                'unit_discount' => $unitDiscount,
                'score' => $this->specificityScore($set, $item),
            ];
        }

        usort($candidates, function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return $b['unit_discount'] <=> $a['unit_discount'];
            }

            return $b['score'] <=> $a['score'];
        });

        $winner = $candidates[0] ?? null;
        $unitPrice = $list;
        $label = null;
        $setId = null;
        $explanation = null;

        if ($winner !== null) {
            /** @var ManufacturerDiscountSet $set */
            $set = $winner['set'];
            $unitPrice = max(0, round($list - (float) $winner['unit_discount'], 2));
            $label = $set->name;
            $setId = $set->id;
            $eligible = array_values(array_filter(
                $allItems,
                fn (array $other): bool => $this->setCoversItem($set, $other, $regionId)
            ));
            $explanation = $this->buildExplanation($set, $item, $eligible, $list, $unitPrice);
        }

        return [
            'product_id' => (int) $item['product_id'],
            'category_id' => $item['category_id'] ?? null,
            'quantity' => $qty,
            'list_unit_price' => $list,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * $qty, 2),
            'discount_amount' => round(($list - $unitPrice) * $qty, 2),
            'has_discount' => $list > $unitPrice + 0.009,
            'discount_set_id' => $setId,
            'discount_label' => $label,
            'promo_code_id' => null,
            'explanation' => $explanation,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $reasons
     */
    public function matchesAudience(
        ManufacturerDiscountSet $set,
        DistributorProfile $distributor,
        ?int $regionId = null,
        array &$reasons = [],
    ): bool {
        if ($set->only_new_distributors && ! $this->isNewDistributor($set, $distributor)) {
            $reasons[] = 'Скидка только для новых дистрибьюторов.';

            return false;
        }

        return match ($set->audience_type) {
            ManufacturerDiscountSet::AUDIENCE_SELECTED => $this->matchesSelected($set, $distributor, $reasons),
            ManufacturerDiscountSet::AUDIENCE_REGION => $this->matchesAudienceRegion($set, $distributor, $reasons),
            ManufacturerDiscountSet::AUDIENCE_EXCLUSIVE => $this->matchesExclusive($set, $distributor, $regionId, $reasons),
            ManufacturerDiscountSet::AUDIENCE_COMPANY_TYPES => $this->matchesCompanyType($set, $distributor, $reasons),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $reasons
     */
    public function setCoversItem(ManufacturerDiscountSet $set, array $item, ?int $regionId, array &$reasons = []): bool
    {
        if ($set->apply_only_active_products) {
            $status = (string) ($item['status'] ?? Product::STATUS_ACTIVE);
            if ($status !== '' && $status !== Product::STATUS_ACTIVE) {
                $reasons[] = 'Скидка действует только на активные товары.';

                return false;
            }
        }

        $setRegions = $set->intList($set->region_ids);
        if ($setRegions !== [] && ($regionId === null || ! in_array((int) $regionId, $setRegions, true))) {
            $reasons[] = 'Регион не входит в область действия скидки.';

            return false;
        }

        $productIds = $set->intList($set->product_ids);
        $productId = (int) $item['product_id'];
        if ($productIds !== [] && in_array($productId, $productIds, true)) {
            return true;
        }

        $excluded = $set->intList($set->excluded_category_ids);
        $categoryId = isset($item['category_id']) ? (int) $item['category_id'] : 0;
        if ($categoryId > 0 && $excluded !== [] && $this->categoryMatches($categoryId, $excluded, (int) $set->manufacturer_profile_id)) {
            $reasons[] = 'Категория товара исключена из набора.';

            return false;
        }

        // «Ко всем товарам» перекрывает списки категорий/позиций (они могли остаться в форме скрытыми).
        if ($set->apply_to_all_products) {
            return true;
        }

        $categories = $set->intList($set->category_ids);
        if ($categories !== [] && $categoryId > 0 && $this->categoryMatches($categoryId, $categories, (int) $set->manufacturer_profile_id)) {
            return true;
        }

        if ($productIds !== [] || $categories !== []) {
            $reasons[] = 'Товар не входит в выбранные категории или позиции.';

            return false;
        }

        $reasons[] = 'Товар не входит в выбранные категории или позиции.';

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $eligibleItems
     * @param  list<string>  $reasons
     */
    public function conditionsMet(ManufacturerDiscountSet $set, array $eligibleItems, array &$reasons = []): bool
    {
        $qty = array_sum(array_map(fn (array $item): int => (int) $item['quantity'], $eligibleItems));
        $amount = array_sum(array_map(
            fn (array $item): float => (float) $item['list_unit_price'] * (int) $item['quantity'],
            $eligibleItems
        ));

        $minQty = $set->min_quantity ? (int) $set->min_quantity : null;
        $minAmount = $set->min_order_amount !== null ? (float) $set->min_order_amount : null;

        $qtyOk = $minQty === null || $qty >= $minQty;
        $amountOk = $minAmount === null || $amount + 0.0001 >= $minAmount;

        if ($minQty === null && $minAmount === null) {
            return true;
        }

        $ok = $set->require_all_conditions ? ($qtyOk && $amountOk) : ($qtyOk || $amountOk);
        if ($ok) {
            return true;
        }

        if (! $qtyOk && $minQty !== null) {
            $reasons[] = 'Количество товаров '.$qty.' меньше минимума '.$minQty.'.';
        }
        if (! $amountOk && $minAmount !== null) {
            $reasons[] = 'Сумма заказа '.number_format($amount, 0, ',', ' ').' ₽ меньше минимума '.number_format($minAmount, 0, ',', ' ').' ₽.';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $eligibleItems
     */
    public function unitDiscountForSet(ManufacturerDiscountSet $set, array $item, array $eligibleItems): float
    {
        $list = (float) $item['list_unit_price'];

        if ($set->type === ManufacturerDiscountSet::TYPE_VOLUME_TIER) {
            $qty = array_sum(array_map(fn (array $row): int => (int) $row['quantity'], $eligibleItems));
            $amount = array_sum(array_map(
                fn (array $row): float => (float) $row['list_unit_price'] * (int) $row['quantity'],
                $eligibleItems
            ));
            $tier = $this->matchingTier($set, $amount, $qty);
            if ($tier === null) {
                return 0.0;
            }
            if ($tier['percent'] !== null) {
                return round($list * ((float) $tier['percent'] / 100), 2);
            }
            if ($tier['amount'] !== null) {
                return min($list, (float) $tier['amount']);
            }

            return 0.0;
        }

        if ($set->type === ManufacturerDiscountSet::TYPE_FIXED || (float) ($set->discount_amount ?? 0) > 0 && (float) ($set->discount_percent ?? 0) <= 0) {
            if ($set->discount_amount !== null) {
                return min($list, (float) $set->discount_amount);
            }
        }

        if ($set->discount_percent !== null) {
            return round($list * ((float) $set->discount_percent / 100), 2);
        }

        if ($set->discount_amount !== null) {
            return min($list, (float) $set->discount_amount);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function specificityScore(ManufacturerDiscountSet $set, array $item): int
    {
        $score = 0;
        $productIds = $set->intList($set->product_ids);
        if (in_array((int) $item['product_id'], $productIds, true)) {
            $score += 100;
        } elseif ($set->intList($set->category_ids) !== []) {
            $score += 50;
        } elseif ($set->apply_to_all_products) {
            $score += 10;
        }

        $score += match ($set->audience_type) {
            ManufacturerDiscountSet::AUDIENCE_SELECTED => 40,
            ManufacturerDiscountSet::AUDIENCE_EXCLUSIVE => 30,
            ManufacturerDiscountSet::AUDIENCE_COMPANY_TYPES => 20,
            ManufacturerDiscountSet::AUDIENCE_REGION => 15,
            default => 0,
        };

        if ($set->type === ManufacturerDiscountSet::TYPE_INDIVIDUAL) {
            $score += 20;
        }
        if ($set->intList($set->region_ids) !== []) {
            $score += 8;
        }

        return $score;
    }

    /**
     * @return Collection<int, ManufacturerDiscountSet>
     */
    private function activeSets(int $manufacturerProfileId): Collection
    {
        if (! isset($this->setsCache[$manufacturerProfileId])) {
            $sets = ManufacturerDiscountSet::query()
                ->forManufacturer($manufacturerProfileId)
                ->where('status', ManufacturerDiscountSet::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->get();

            foreach ($sets as $set) {
                if ($set->effectiveStatus() === ManufacturerDiscountSet::STATUS_COMPLETED) {
                    if ($set->auto_deactivate || $set->ends_at !== null) {
                        $set->forceFill(['status' => ManufacturerDiscountSet::STATUS_INACTIVE])->save();
                    }
                }
            }

            $this->setsCache[$manufacturerProfileId] = $sets
                ->filter(fn (ManufacturerDiscountSet $set): bool => $set->isCurrentlyApplicable())
                ->values();
        }

        return $this->setsCache[$manufacturerProfileId];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function resolvePromo(
        int $manufacturerProfileId,
        ?string $promoCode,
        DistributorProfile $distributor,
        ?int $regionId,
        array $items,
        ?User $user,
    ): ?ManufacturerPromoCode {
        $code = strtoupper(trim((string) $promoCode));
        if ($code === '') {
            return null;
        }

        $promo = ManufacturerPromoCode::query()
            ->forManufacturer($manufacturerProfileId)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->first();

        if ($promo === null) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод не найден.',
            ]);
        }

        if (! $promo->isCurrentlyApplicable()) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод недействителен или срок его действия истёк.',
            ]);
        }

        $companyIds = $promo->intList($promo->company_ids);
        if ($companyIds !== [] && ! in_array((int) $distributor->id, $companyIds, true)) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод недоступен для вашей компании.',
            ]);
        }

        $regions = $promo->intList($promo->region_ids);
        if ($regions !== [] && ($regionId === null || ! in_array((int) $regionId, $regions, true))) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод недоступен в выбранном регионе.',
            ]);
        }

        $subtotal = array_sum(array_map(
            fn (array $item): float => (float) $item['list_unit_price'] * (int) $item['quantity'],
            array_filter($items, fn (array $item): bool => $this->promoAppliesToItem($promo, $item))
        ));

        if ($promo->min_order_amount !== null && $subtotal + 0.0001 < (float) $promo->min_order_amount) {
            throw ValidationException::withMessages([
                'promo_code' => 'Минимальная сумма заказа для промокода: '.number_format((float) $promo->min_order_amount, 2, ',', ' ').' ₽.',
            ]);
        }

        if ($user && (int) $promo->per_user_limit > 0) {
            $used = $promo->usages()->where('user_id', $user->id)->count();
            if ($used >= (int) $promo->per_user_limit) {
                throw ValidationException::withMessages([
                    'promo_code' => 'Вы уже использовали этот промокод.',
                ]);
            }
        }

        if ($subtotal <= 0) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод нельзя применить к товарам в заказе.',
            ]);
        }

        return $promo;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function promoAppliesToItem(ManufacturerPromoCode $promo, array $item): bool
    {
        $productIds = $promo->intList($promo->product_ids);
        $categoryIds = $promo->intList($promo->category_ids);
        if ($promo->apply_to_all_products && $productIds === [] && $categoryIds === []) {
            return true;
        }
        if ($productIds !== [] && in_array((int) $item['product_id'], $productIds, true)) {
            return true;
        }
        $categoryId = isset($item['category_id']) ? (int) $item['category_id'] : 0;
        if ($categoryIds !== [] && $categoryId > 0 && $this->categoryMatches($categoryId, $categoryIds, (int) $promo->manufacturer_profile_id)) {
            return true;
        }

        return false;
    }

    private function promoUnitDiscount(ManufacturerPromoCode $promo, float $list): float
    {
        if ($promo->discount_type === ManufacturerPromoCode::TYPE_FIXED) {
            return min($list, (float) $promo->discount_value);
        }

        return round($list * ((float) $promo->discount_value / 100), 2);
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesSelected(ManufacturerDiscountSet $set, DistributorProfile $distributor, array &$reasons): bool
    {
        $ids = $set->intList($set->distributor_ids);
        if ($ids === [] || ! in_array((int) $distributor->id, $ids, true)) {
            $reasons[] = 'Дистрибьютор не входит в список получателей.';

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesAudienceRegion(ManufacturerDiscountSet $set, DistributorProfile $distributor, array &$reasons): bool
    {
        $ids = $set->intList($set->audience_region_ids ?: $set->region_ids);
        if ($ids === []) {
            $reasons[] = 'Не заданы регионы аудитории.';

            return false;
        }

        $has = $distributor->regions()->whereIn('regions.id', $ids)->exists();
        if (! $has) {
            $reasons[] = 'Регион дистрибьютора не совпадает с условиями набора.';
        }

        return $has;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesExclusive(
        ManufacturerDiscountSet $set,
        DistributorProfile $distributor,
        ?int $regionId,
        array &$reasons,
    ): bool {
        $query = ManufacturerDistributorExclusiveRegion::query()
            ->where('manufacturer_profile_id', $set->manufacturer_profile_id)
            ->where('distributor_profile_id', $distributor->id);

        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        $has = $query->exists();
        if (! $has) {
            $reasons[] = 'Дистрибьютор не является эксклюзивным в этом регионе.';
        }

        return $has;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesCompanyType(ManufacturerDiscountSet $set, DistributorProfile $distributor, array &$reasons): bool
    {
        $types = array_values(array_filter(array_map('strval', $set->company_types ?? [])));
        if ($types === [] || ! in_array((string) $distributor->legal_form, $types, true)) {
            $reasons[] = 'Организационно-правовая форма компании не входит в условия.';

            return false;
        }

        return true;
    }

    private function isNewDistributor(ManufacturerDiscountSet $set, DistributorProfile $distributor): bool
    {
        $days = (int) config('manufacturer_discounts.new_distributor_days', 90);
        $partnership = ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $set->manufacturer_profile_id)
            ->where('distributor_profile_id', $distributor->id)
            ->first();

        if ($partnership === null || $partnership->added_at === null) {
            return false;
        }

        if ($partnership->added_at->lt(now()->subDays($days))) {
            return false;
        }

        $hasOrders = PlatformOrder::query()
            ->where('manufacturer_profile_id', $set->manufacturer_profile_id)
            ->where('distributor_profile_id', $distributor->id)
            ->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)
            ->whereNotIn('status', [PlatformOrder::STATUS_REJECTED])
            ->exists();

        return ! $hasOrders;
    }

    /**
     * @param  list<int>  $allowed
     */
    private function categoryMatches(int $categoryId, array $allowed, int $cacheKey): bool
    {
        if (in_array($categoryId, $allowed, true)) {
            return true;
        }

        $allowed = array_values($allowed);
        sort($allowed);
        $mapKey = md5($cacheKey.'|'.implode(',', $allowed));
        if (! isset($this->categoryDescendantsCache[$cacheKey][$mapKey])) {
            $ids = $allowed;
            $pending = $allowed;
            while ($pending !== []) {
                $children = ProductCategory::query()
                    ->whereIn('parent_id', $pending)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $new = array_values(array_diff($children, $ids));
                $ids = array_merge($ids, $new);
                $pending = $new;
            }
            $this->categoryDescendantsCache[$cacheKey][$mapKey] = $ids;
        }

        return in_array($categoryId, $this->categoryDescendantsCache[$cacheKey][$mapKey], true);
    }

    /**
     * @return array{min_amount:?float,min_quantity:?int,percent:?float,amount:?float}|null
     */
    private function matchingTier(ManufacturerDiscountSet $set, float $amount, int $qty): ?array
    {
        $matched = null;
        foreach ($set->normalizedTiers() as $tier) {
            $amountOk = $tier['min_amount'] === null || $amount + 0.0001 >= (float) $tier['min_amount'];
            $qtyOk = $tier['min_quantity'] === null || $qty >= (int) $tier['min_quantity'];
            if ($amountOk && $qtyOk) {
                $matched = $tier;
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $eligible
     */
    private function buildExplanation(
        ManufacturerDiscountSet $set,
        array $item,
        array $eligible,
        float $list,
        float $unitPrice,
    ): string {
        $percent = $list > 0 ? round((($list - $unitPrice) / $list) * 100, 1) : 0;
        $amount = array_sum(array_map(
            fn (array $row): float => (float) $row['list_unit_price'] * (int) $row['quantity'],
            $eligible
        ));

        if ($set->type === ManufacturerDiscountSet::TYPE_VOLUME_TIER) {
            $tier = $this->matchingTier(
                $set,
                $amount,
                array_sum(array_map(fn (array $row): int => (int) $row['quantity'], $eligible))
            );
            $thresholdLabel = isset($tier['min_amount']) && $tier['min_amount'] !== null
                ? number_format((float) $tier['min_amount'], 0, ',', ' ').' ₽'
                : ((string) ($tier['min_quantity'] ?? 0)).' шт.';

            return 'Скидка '.$percent.'% применена, потому что сумма заказа '
                .number_format($amount, 0, ',', ' ').' ₽ превышает минимальный порог '.$thresholdLabel.'.';
        }

        if ($set->min_order_amount !== null && $amount + 0.0001 >= (float) $set->min_order_amount) {
            return 'Скидка '.$percent.'% применена, потому что сумма заказа '
                .number_format($amount, 0, ',', ' ').' ₽ превышает минимальный порог '
                .number_format((float) $set->min_order_amount, 0, ',', ' ').' ₽.';
        }

        if ($set->discount_amount !== null && (float) $set->discount_amount > 0 && $set->type === ManufacturerDiscountSet::TYPE_FIXED) {
            return 'Скидка '.number_format((float) $set->discount_amount, 2, ',', ' ').' ₽ применена по набору «'.$set->name.'».';
        }

        return 'Скидка '.$percent.'% применена по набору «'.$set->name.'».';
    }

    /**
     * @return array{applies: bool, unit_price: float, list_unit_price: float, discount_amount: float, explanation: string}
     */
    private function previewFail(float $list, int $quantity, string $explanation): array
    {
        return [
            'applies' => false,
            'unit_price' => $list,
            'list_unit_price' => $list,
            'discount_amount' => 0.0,
            'explanation' => $explanation,
        ];
    }
}

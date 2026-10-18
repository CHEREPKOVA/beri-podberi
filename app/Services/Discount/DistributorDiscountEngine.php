<?php

namespace App\Services\Discount;

use App\Models\DistributorDiscountSet;
use App\Models\DistributorProduct;
use App\Models\EndCompanyProfile;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

class DistributorDiscountEngine
{
    /** @var array<int, Collection<int, DistributorDiscountSet>> */
    private array $setsCache = [];

    /** @var array<int, array<string, list<int>>> */
    private array $categoryDescendantsCache = [];

    /**
     * @param  list<array{
     *     distributor_product_id:int,
     *     category_id:?int,
     *     brand:?string,
     *     quantity:int,
     *     list_unit_price:float,
     *     warehouse_id:?int,
     *     status?:?string
     * }>  $items
     * @return array{
     *     items: list<array<string, mixed>>,
     *     discount_amount: float,
     *     discount_label: ?string,
     *     discount_set_id: ?int,
     *     explanations: list<string>
     * }
     */
    public function quote(
        int $distributorProfileId,
        EndCompanyProfile $company,
        array $items,
        ?int $regionId = null,
    ): array {
        $sets = $this->activeSets($distributorProfileId)
            ->filter(fn (DistributorDiscountSet $set): bool => $this->matchesAudience($set, $company, $regionId))
            ->values();

        $quoted = [];
        foreach ($items as $index => $item) {
            $quoted[$index] = $this->quoteItem($sets, $item, $items);
        }

        $setNames = collect($quoted)
            ->pluck('discount_label')
            ->filter()
            ->unique()
            ->values();

        $primarySetId = collect($quoted)->pluck('discount_set_id')->filter()->countBy()->sortDesc()->keys()->first();
        $totalDiscount = round(array_sum(array_map(fn (array $row): float => (float) ($row['discount_amount'] ?? 0), $quoted)), 2);
        $explanations = collect($quoted)->pluck('explanation')->filter()->unique()->values()->all();

        return [
            'items' => array_values($quoted),
            'discount_amount' => $totalDiscount,
            'discount_label' => $setNames->implode(', ') ?: null,
            'discount_set_id' => $primarySetId ? (int) $primarySetId : null,
            'explanations' => $explanations,
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
        DistributorDiscountSet $set,
        EndCompanyProfile $company,
        DistributorProduct $product,
        int $quantity,
        ?int $regionId = null,
        ?int $warehouseId = null,
    ): array {
        $list = (float) ($product->retail_price ?? 0);
        $quantity = max(1, $quantity);
        $item = [
            'distributor_product_id' => (int) $product->id,
            'category_id' => $product->product_category_id ? (int) $product->product_category_id : null,
            'brand' => $product->brand,
            'quantity' => $quantity,
            'list_unit_price' => $list,
            'warehouse_id' => $warehouseId,
            'status' => $product->status,
        ];

        $reasons = [];
        if (! $this->matchesAudience($set, $company, $regionId, $reasons)) {
            return $this->previewFail($list, 'Набор не применяется к выбранному клиенту. '.implode(' ', $reasons));
        }

        if (! $this->setCoversItem($set, $item, $reasons)) {
            return $this->previewFail($list, 'Набор не покрывает выбранный товар. '.implode(' ', $reasons));
        }

        if (! $this->conditionsMet($set, [$item], $reasons)) {
            return $this->previewFail($list, implode(' ', $reasons) ?: 'Условия набора не выполнены.');
        }

        $unitDiscount = $this->unitDiscountForSet($set, $item);
        $unitPrice = max(0, round($list - $unitDiscount, 2));
        $lineDiscount = round(($list - $unitPrice) * $quantity, 2);

        return [
            'applies' => $lineDiscount > 0,
            'unit_price' => $unitPrice,
            'list_unit_price' => $list,
            'discount_amount' => $lineDiscount,
            'explanation' => $this->buildExplanation($set, $list, $unitPrice),
        ];
    }

    /**
     * @param  Collection<int, DistributorDiscountSet>  $sets
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $allItems
     * @return array<string, mixed>
     */
    private function quoteItem(Collection $sets, array $item, array $allItems): array
    {
        $list = (float) $item['list_unit_price'];
        $qty = max(1, (int) $item['quantity']);
        $candidates = [];

        foreach ($sets as $set) {
            if (! $this->setCoversItem($set, $item)) {
                continue;
            }
            $eligible = array_values(array_filter(
                $allItems,
                fn (array $other): bool => $this->setCoversItem($set, $other)
            ));
            if (! $this->conditionsMet($set, $eligible)) {
                continue;
            }

            $unitDiscount = $this->unitDiscountForSet($set, $item);
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
            /** @var DistributorDiscountSet $set */
            $set = $winner['set'];
            $unitPrice = max(0, round($list - (float) $winner['unit_discount'], 2));
            $label = $set->name;
            $setId = $set->id;
            $explanation = $this->buildExplanation($set, $list, $unitPrice);
        }

        return [
            'distributor_product_id' => (int) $item['distributor_product_id'],
            'quantity' => $qty,
            'list_unit_price' => $list,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * $qty, 2),
            'discount_amount' => round(($list - $unitPrice) * $qty, 2),
            'has_discount' => $list > $unitPrice + 0.009,
            'discount_set_id' => $setId,
            'discount_label' => $label,
            'explanation' => $explanation,
        ];
    }

    /**
     * @param  list<string>  $reasons
     */
    public function matchesAudience(
        DistributorDiscountSet $set,
        EndCompanyProfile $company,
        ?int $regionId = null,
        array &$reasons = [],
    ): bool {
        if (! in_array($set->type, [DistributorDiscountSet::TYPE_PERSONAL, DistributorDiscountSet::TYPE_MASS], true)) {
            return true;
        }

        return match ($set->audience_type) {
            DistributorDiscountSet::AUDIENCE_SELECTED => $this->matchesSelected($set, $company, $reasons),
            DistributorDiscountSet::AUDIENCE_REGION => $this->matchesRegion($set, $company, $regionId, $reasons),
            DistributorDiscountSet::AUDIENCE_COMPANY_TYPES => $this->matchesCompanyType($set, $company, $reasons),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $reasons
     */
    public function setCoversItem(DistributorDiscountSet $set, array $item, array &$reasons = []): bool
    {
        $warehouses = $set->intList($set->warehouse_ids);
        $warehouseId = isset($item['warehouse_id']) ? (int) $item['warehouse_id'] : 0;
        if ($warehouses !== [] && ($warehouseId <= 0 || ! in_array($warehouseId, $warehouses, true))) {
            $reasons[] = 'Скидка действует только на выбранном складе.';

            return false;
        }

        if ($set->apply_to_all_products) {
            return true;
        }

        $productIds = $set->intList($set->product_ids);
        $productId = (int) $item['distributor_product_id'];
        if ($productIds !== [] && in_array($productId, $productIds, true)) {
            return true;
        }

        $brands = array_map('mb_strtolower', $set->stringList($set->brands));
        $brand = mb_strtolower(trim((string) ($item['brand'] ?? '')));
        if ($brands !== [] && $brand !== '' && in_array($brand, $brands, true)) {
            return true;
        }

        $categories = $set->intList($set->category_ids);
        $categoryId = isset($item['category_id']) ? (int) $item['category_id'] : 0;
        if ($categories !== [] && $categoryId > 0 && $this->categoryMatches($categoryId, $categories, (int) $set->distributor_profile_id)) {
            return true;
        }

        $reasons[] = 'Товар не входит в выбранные категории, бренды или позиции.';

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $eligibleItems
     * @param  list<string>  $reasons
     */
    public function conditionsMet(DistributorDiscountSet $set, array $eligibleItems, array &$reasons = []): bool
    {
        $qty = array_sum(array_map(fn (array $item): int => (int) $item['quantity'], $eligibleItems));
        $amount = array_sum(array_map(
            fn (array $item): float => (float) $item['list_unit_price'] * (int) $item['quantity'],
            $eligibleItems
        ));

        $minQty = $set->min_quantity ? (int) $set->min_quantity : null;
        $minAmount = $set->min_order_amount !== null ? (float) $set->min_order_amount : null;

        if ($minQty !== null && $qty < $minQty) {
            $reasons[] = 'Количество товаров '.$qty.' меньше минимума '.$minQty.'.';

            return false;
        }

        if ($minAmount !== null && $amount + 0.0001 < $minAmount) {
            $reasons[] = 'Сумма заказа '.number_format($amount, 0, ',', ' ').' ₽ меньше минимума '.number_format($minAmount, 0, ',', ' ').' ₽.';

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function unitDiscountForSet(DistributorDiscountSet $set, array $item): float
    {
        $list = (float) $item['list_unit_price'];

        if ($set->value_kind === DistributorDiscountSet::VALUE_FIXED || ((float) ($set->discount_amount ?? 0) > 0 && (float) ($set->discount_percent ?? 0) <= 0)) {
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
    private function specificityScore(DistributorDiscountSet $set, array $item): int
    {
        $score = (int) $set->priority * 10;
        if ($set->do_not_stack) {
            $score += 1000;
        }

        $productIds = $set->intList($set->product_ids);
        if (in_array((int) $item['distributor_product_id'], $productIds, true)) {
            $score += 100;
        } elseif ($set->intList($set->category_ids) !== []) {
            $score += 50;
        } elseif ($set->stringList($set->brands) !== []) {
            $score += 40;
        } elseif ($set->apply_to_all_products) {
            $score += 10;
        }

        $score += match ($set->type) {
            DistributorDiscountSet::TYPE_PERSONAL => 80,
            DistributorDiscountSet::TYPE_MASS => 30,
            DistributorDiscountSet::TYPE_PRODUCTS => 20,
            default => 0,
        };

        return $score;
    }

    /**
     * @return Collection<int, DistributorDiscountSet>
     */
    private function activeSets(int $distributorProfileId): Collection
    {
        if (! isset($this->setsCache[$distributorProfileId])) {
            $sets = DistributorDiscountSet::query()
                ->forDistributor($distributorProfileId)
                ->where('status', DistributorDiscountSet::STATUS_ACTIVE)
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->get();

            foreach ($sets as $set) {
                if ($set->effectiveStatus() === DistributorDiscountSet::STATUS_COMPLETED && $set->auto_deactivate) {
                    $set->forceFill(['status' => DistributorDiscountSet::STATUS_INACTIVE])->save();
                }
            }

            $this->setsCache[$distributorProfileId] = $sets
                ->filter(fn (DistributorDiscountSet $set): bool => $set->isCurrentlyApplicable())
                ->values();
        }

        return $this->setsCache[$distributorProfileId];
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesSelected(DistributorDiscountSet $set, EndCompanyProfile $company, array &$reasons): bool
    {
        $ids = $set->intList($set->company_ids);
        if ($ids === [] || ! in_array((int) $company->id, $ids, true)) {
            $reasons[] = 'Компания не входит в список получателей.';

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesRegion(
        DistributorDiscountSet $set,
        EndCompanyProfile $company,
        ?int $regionId,
        array &$reasons,
    ): bool {
        $ids = $set->intList($set->region_ids);
        if ($ids === []) {
            $reasons[] = 'Не заданы регионы аудитории.';

            return false;
        }

        $companyRegions = $company->regionIds();
        if ($regionId !== null) {
            $companyRegions[] = (int) $regionId;
        }

        $has = array_intersect($ids, array_map('intval', $companyRegions)) !== [];
        if (! $has) {
            $reasons[] = 'Регион клиента не совпадает с условиями набора.';
        }

        return $has;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function matchesCompanyType(DistributorDiscountSet $set, EndCompanyProfile $company, array &$reasons): bool
    {
        $types = array_values(array_filter(array_map('strval', $set->company_types ?? [])));
        if ($types === [] || ! in_array((string) $company->legal_form, $types, true)) {
            $reasons[] = 'Тип компании не входит в условия.';

            return false;
        }

        return true;
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
        $mapKey = md5(implode(',', $allowed));
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

    private function buildExplanation(DistributorDiscountSet $set, float $list, float $unitPrice): string
    {
        if ($set->value_kind === DistributorDiscountSet::VALUE_FIXED) {
            return 'Скидка '.number_format((float) $set->discount_amount, 2, ',', ' ').' ₽ применена по набору «'.$set->name.'».';
        }

        $percent = $list > 0 ? round((($list - $unitPrice) / $list) * 100, 1) : 0;

        return 'Скидка '.$percent.'% применена по набору «'.$set->name.'».';
    }

    /**
     * @return array{applies: bool, unit_price: float, list_unit_price: float, discount_amount: float, explanation: string}
     */
    private function previewFail(float $list, string $explanation): array
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

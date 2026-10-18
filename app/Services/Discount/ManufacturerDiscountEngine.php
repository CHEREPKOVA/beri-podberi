<?php

namespace App\Services\Discount;

use App\Models\DistributorProfile;
use App\Models\ManufacturerDiscountSet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class ManufacturerDiscountEngine
{
    public function __construct(
        private readonly DiscountEngine $engine,
    ) {}

    /**
     * @param  list<array{key?: mixed, product: Product, quantity: int, list_unit_price: float}>  $items
     * @return array{
     *     items: list<array<string, mixed>>,
     *     discount_amount: float,
     *     applied_sets: list<array{id: int, name: string}>,
     *     promo: ?array{id: int, code: string, name: ?string},
     *     explanations: list<string>
     * }
     */
    public function quoteItems(
        int $manufacturerProfileId,
        DistributorProfile $distributor,
        array $items,
        ?int $regionId = null,
        ?string $promoCode = null,
        ?User $user = null,
    ): array {
        $payload = [];
        $keys = [];
        foreach (array_values($items) as $index => $item) {
            $product = $item['product'];
            $keys[$index] = $item['key'] ?? $index;
            $payload[] = [
                'product_id' => (int) $product->id,
                'category_id' => $product->category_id ? (int) $product->category_id : null,
                'quantity' => max(1, (int) $item['quantity']),
                'list_unit_price' => (float) $item['list_unit_price'],
                'status' => (string) $product->status,
            ];
        }

        $quoted = $this->engine->quote(
            $manufacturerProfileId,
            $distributor,
            $payload,
            $regionId,
            $promoCode,
            $user,
        );

        $rows = [];
        foreach ($quoted['items'] as $index => $row) {
            $row['key'] = $keys[$index] ?? $index;
            $row['discount_explanation'] = $row['explanation'] ?? null;
            $rows[] = $row;
        }

        $applied = [];
        foreach ($rows as $row) {
            if (! empty($row['discount_set_id']) && ! empty($row['discount_label'])) {
                $applied[(int) $row['discount_set_id']] = [
                    'id' => (int) $row['discount_set_id'],
                    'name' => (string) $row['discount_label'],
                ];
            }
        }

        $promo = null;
        if (! empty($quoted['promo_code_id'])) {
            $promo = [
                'id' => (int) $quoted['promo_code_id'],
                'code' => (string) $quoted['promo_code'],
                'name' => (string) $quoted['promo_code'],
                'discount_amount' => (float) ($quoted['promo_discount_amount'] ?? 0),
            ];
        }

        return [
            'items' => $rows,
            'discount_amount' => (float) $quoted['discount_amount'],
            'applied_sets' => array_values($applied),
            'promo' => $promo,
            'explanations' => $quoted['explanations'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteProduct(
        Product $product,
        DistributorProfile $distributor,
        ?int $regionId,
        int $quantity = 1,
        ?string $promoCode = null,
        ?User $user = null,
    ): array {
        $list = (float) $product->getPriceForRegion($regionId);
        $quoted = $this->quoteItems(
            (int) $product->manufacturer_profile_id,
            $distributor,
            [[
                'key' => $product->id,
                'product' => $product,
                'quantity' => $quantity,
                'list_unit_price' => $list,
            ]],
            $regionId,
            $promoCode,
            $user,
        );

        return $quoted['items'][0] + [
            'explanations' => $quoted['explanations'],
            'promo' => $quoted['promo'],
        ];
    }

    /**
     * @return array{applies: bool, unit_price: float, list_unit_price: float, discount_amount: float, explanation: string, label: ?string}
     */
    public function preview(
        ManufacturerDiscountSet $set,
        DistributorProfile $distributor,
        Product $product,
        int $quantity,
        ?int $regionId = null,
    ): array {
        $result = $this->engine->preview($set, $distributor, $product, $quantity, $regionId);

        return $result + [
            'label' => $result['applies'] ? $set->name : null,
        ];
    }

    /**
     * @return Collection<int, ManufacturerDiscountSet>
     */
    public function overlappingSets(ManufacturerDiscountSet $set): Collection
    {
        return ManufacturerDiscountSet::query()
            ->forManufacturer((int) $set->manufacturer_profile_id)
            ->where('id', '!=', $set->id)
            ->where('status', ManufacturerDiscountSet::STATUS_ACTIVE)
            ->get()
            ->filter(fn (ManufacturerDiscountSet $other): bool => $other->isCurrentlyApplicable())
            ->values();
    }
}

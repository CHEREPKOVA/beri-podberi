<?php

namespace App\Services\Catalog;

final class CatalogListingParams
{
    public const SEARCH_SCOPE_CATEGORY = 'category';

    public const SEARCH_SCOPE_GLOBAL = 'global';

    public const STOCK_IN_STOCK = 'in_stock';

    public const STOCK_ON_ORDER = 'on_order';

    public const STOCK_OUT_OF_STOCK = 'out_of_stock';

    /**
     * @param  array<int, int|string>  $attributeFilters
     * @param  list<int>  $distributorIds
     * @param  list<int>  $manufacturerIds
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly string $searchScope = self::SEARCH_SCOPE_CATEGORY,
        public readonly array $attributeFilters = [],
        public readonly array $distributorIds = [],
        public readonly array $manufacturerIds = [],
        public readonly ?string $stock = null,
        public readonly ?float $priceMin = null,
        public readonly ?float $priceMax = null,
        public readonly int $perPage = 24,
    ) {}

    public function hasStructuralFilters(): bool
    {
        return $this->distributorIds !== []
            || $this->manufacturerIds !== []
            || $this->stock !== null
            || $this->priceMin !== null
            || $this->priceMax !== null;
    }

    /**
     * @return list<string>
     */
    public static function stockOptions(): array
    {
        return [
            self::STOCK_IN_STOCK,
            self::STOCK_ON_ORDER,
            self::STOCK_OUT_OF_STOCK,
        ];
    }

    public static function stockLabel(string $stock): string
    {
        return match ($stock) {
            self::STOCK_IN_STOCK => 'В наличии',
            self::STOCK_ON_ORDER => 'Под заказ',
            self::STOCK_OUT_OF_STOCK => 'Нет на складе',
            default => $stock,
        };
    }
}

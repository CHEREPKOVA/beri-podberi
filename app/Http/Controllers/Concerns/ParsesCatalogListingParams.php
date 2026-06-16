<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Catalog\CatalogListingParams;
use Illuminate\Http\Request;

trait ParsesCatalogListingParams
{
    use ParsesCatalogAttributeFilters;

    protected function parseCatalogListingParams(Request $request): CatalogListingParams
    {
        $search = $request->filled('search')
            ? trim($request->string('search')->toString())
            : null;
        if ($search === '') {
            $search = null;
        }

        $searchScope = $request->string('search_scope', CatalogListingParams::SEARCH_SCOPE_CATEGORY)->toString();
        if (! in_array($searchScope, [CatalogListingParams::SEARCH_SCOPE_CATEGORY, CatalogListingParams::SEARCH_SCOPE_GLOBAL], true)) {
            $searchScope = CatalogListingParams::SEARCH_SCOPE_CATEGORY;
        }

        $stock = $request->filled('stock')
            ? $request->string('stock')->toString()
            : null;
        if ($stock !== null && ! in_array($stock, CatalogListingParams::stockOptions(), true)) {
            $stock = null;
        }

        $priceMin = $request->filled('price_min') ? (float) $request->input('price_min') : null;
        $priceMax = $request->filled('price_max') ? (float) $request->input('price_max') : null;
        if ($priceMin !== null && $priceMin < 0) {
            $priceMin = null;
        }
        if ($priceMax !== null && $priceMax < 0) {
            $priceMax = null;
        }

        return new CatalogListingParams(
            search: $search,
            searchScope: $searchScope,
            attributeFilters: $this->parseCatalogAttributeFilters($request),
            distributorIds: $this->parsePositiveIntList($request->input('distributor_ids', [])),
            manufacturerIds: $this->parsePositiveIntList($request->input('manufacturer_ids', [])),
            stock: $stock,
            priceMin: $priceMin,
            priceMax: $priceMax,
        );
    }

    /**
     * @return list<int>
     */
    private function parsePositiveIntList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value !== null && $value !== '' ? [$value] : [];
        }

        return collect($value)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}

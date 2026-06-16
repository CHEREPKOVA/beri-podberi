<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Support\Collection;

class CatalogEmptyStateService
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
    ) {}

    /**
     * @return array{
     *     show_reset: bool,
     *     suggested_categories: list<array{id: int, name: string, slug: string, count: int, url: string}>,
     *     similar_query: ?string,
     *     similar_query_count: int,
     *     suggested_analogs: list<array{id: int, name: string, sku: ?string, url: string, unavailable: bool}>
     * }
     */
    public function build(
        ?ProductCategory $category,
        CatalogListingParams $params,
        string $catalogIndexRoute,
        string $productShowRoute,
    ): array {
        $showReset = $params->hasStructuralFilters()
            || $params->search !== null
            || $params->attributeFilters !== [];

        $similarQuery = $this->normalizeSimilarQuery($params->search);
        $similarQueryCount = 0;
        if ($similarQuery !== null && $similarQuery !== $params->search) {
            $similarQueryCount = $this->catalog->visibleProductsQuery()
                ->search($similarQuery, $this->searchOptions())
                ->when(
                    $category !== null && $params->searchScope !== CatalogListingParams::SEARCH_SCOPE_GLOBAL,
                    fn ($q) => $q->inCategory($category->id)
                )
                ->count();
        }

        return [
            'show_reset' => $showReset,
            'suggested_categories' => $this->suggestedCategories($category, $params, $catalogIndexRoute),
            'similar_query' => $similarQuery !== $params->search ? $similarQuery : null,
            'similar_query_count' => $similarQueryCount,
            'suggested_analogs' => $this->suggestedAnalogs($category, $params, $productShowRoute),
        ];
    }

    /**
     * @return list<array{id: int, name: string, sku: ?string, url: string, unavailable: bool}>
     */
    private function suggestedAnalogs(
        ?ProductCategory $category,
        CatalogListingParams $params,
        string $productShowRoute,
    ): array {
        if (! EndCompanyCatalogSettings::showUnavailableAnalogs()) {
            return [];
        }

        if (! $this->catalog->isBuyerSideCatalog()) {
            return [];
        }

        if ($params->search === null || mb_strlen(trim($params->search)) < 2) {
            return [];
        }

        $query = $this->catalog->visibleProductsQuery()->search($params->search, $this->searchOptions());
        if ($category !== null && $params->searchScope !== CatalogListingParams::SEARCH_SCOPE_GLOBAL) {
            $query->inCategory($category->id);
        }

        $candidates = $query
            ->with(['images'])
            ->orderBy('name')
            ->limit(8)
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $seen = [];
        $result = [];

        foreach ($candidates as $product) {
            foreach ($this->catalog->resolveVisibleAnalogs($product) as $analog) {
                if (isset($seen[$analog->id])) {
                    continue;
                }
                $seen[$analog->id] = true;
                $result[] = [
                    'id' => $analog->id,
                    'name' => $analog->name,
                    'sku' => $analog->sku ?: $analog->manufacturer_sku,
                    'url' => route($productShowRoute, $analog),
                    'unavailable' => (bool) ($analog->unavailable_in_region ?? false),
                ];

                if (count($result) >= 5) {
                    break 2;
                }
            }
        }

        return $result;
    }

    /**
     * @return list<array{id: int, name: string, slug: string, count: int, url: string}>
     */
    private function suggestedCategories(
        ?ProductCategory $category,
        CatalogListingParams $params,
        string $catalogIndexRoute,
    ): array {
        if ($params->search === null || mb_strlen(trim($params->search)) < 2) {
            return [];
        }

        $query = $this->catalog->visibleProductsQuery()->search($params->search, $this->searchOptions());
        if ($category !== null && $params->searchScope === CatalogListingParams::SEARCH_SCOPE_CATEGORY) {
            return [];
        }

        $rows = Product::query()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->whereIn('products.id', (clone $query)->select('products.id'))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        /** @var Collection<int, ProductCategory> $categories */
        $categories = ProductCategory::query()
            ->whereIn('id', $rows->pluck('category_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($categories, $catalogIndexRoute) {
                $cat = $categories->get($row->category_id);
                if (! $cat) {
                    return null;
                }

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => (int) $row->aggregate,
                    'url' => route($catalogIndexRoute, ['category' => $cat->slug]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeSimilarQuery(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $normalized = preg_replace('/[\s\-_]+/u', '', trim($search));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{allow_id_search: bool}
     */
    private function searchOptions(): array
    {
        $role = $this->catalog->catalogRole()?->slug;

        return [
            'allow_id_search' => in_array($role, [
                Role::SLUG_MANUFACTURER,
                Role::SLUG_ADMIN,
                Role::SLUG_MANAGER,
                Role::SLUG_COMPANY_EMPLOYEE,
            ], true),
        ];
    }
}

<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogListingService
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly User $user,
    ) {}

    /**
     * @return array{
     *     products: LengthAwarePaginator,
     *     categoryTree: Collection,
     *     catalogTreeOpenSlugs: array<string, true>,
     *     filterableAttributes: Collection,
     *     appliedFilters: array,
     *     listingParams: CatalogListingParams,
     *     manufacturerProfileId: ?int,
     *     searchQuery: string,
     *     searchTerms: list<string>,
     *     filterDistributors: Collection,
     *     filterManufacturers: Collection,
     *     visibleStructuralFilters: list<string>,
     *     emptyState: ?array
     * }
     */
    public function build(?ProductCategory $category, CatalogListingParams $params, string $catalogIndexRoute = 'manufacturer.catalog.index'): array
    {
        $categoryTree = $this->catalog->categoryTree();
        $filterableAttributes = $category
            ? ProductAttribute::active()->forCategory($category->id)->filterable()->orderedForFilters()->get()
            : collect();

        $query = $this->applyListingFilters(
            $this->catalog->visibleProductsQuery()
                ->with(['category', 'images', 'manufacturerProfile']),
            $category,
            $params,
        );

        $products = $query->orderBy('name')->paginate($params->perPage)->withQueryString();
        $collection = $products->getCollection()->map(function ($product) {
            $product->setAttribute('has_visible_analogs', $this->catalog->hasVisibleAnalogs($product));

            return $product;
        });
        if ($this->catalog->isEndCompanyCatalog()) {
            $collection = $this->catalog->distributorOffers()->enrichProducts($collection);
        }
        $products->setCollection($collection);

        $filterOptions = new CatalogFilterOptionsService($this->catalog);
        $cacheKeyData = [
            'role' => $this->catalog->catalogRole()?->slug,
            'regionId' => $this->catalog->regionId(),
            'categoryId' => $category?->id,
            'search' => $params->search,
            'searchScope' => $params->searchScope,
            'attributeFilters' => $params->attributeFilters,
            'distributorIds' => $params->distributorIds,
            'manufacturerIds' => $params->manufacturerIds,
            'stock' => $params->stock,
            'priceMin' => $params->priceMin,
            'priceMax' => $params->priceMax,
            'perPage' => $params->perPage,
        ];
        $cacheKeyBase = 'catalog.filters:'.md5(json_encode($cacheKeyData, JSON_UNESCAPED_UNICODE));
        $cacheKeyBase = app(CatalogCacheService::class)->versionedKey($cacheKeyBase);
        $cacheTtl = now()->addMinutes(10);

        $emptyState = null;
        if ($products->isEmpty()) {
            $productShowRoute = str_ends_with($catalogIndexRoute, '.index')
                ? substr($catalogIndexRoute, 0, -strlen('.index')).'.show'
                : $catalogIndexRoute.'.show';
            $emptyState = (new CatalogEmptyStateService($this->catalog))->build(
                $category,
                $params,
                $catalogIndexRoute,
                $productShowRoute,
            );
        }

        if ($params->search !== null && mb_strlen(trim($params->search)) >= 2) {
            app(CatalogSearchLogService::class)->log(
                $this->user,
                $this->catalog->catalogRole()?->slug,
                $this->catalog->regionId(),
                trim($params->search),
                $products->total(),
            );
        }

        return [
            'products' => $products,
            'categoryTree' => $categoryTree,
            'catalogTreeOpenSlugs' => ProductCategory::initialOpenSlugsForCatalogTree($categoryTree, $category),
            'filterableAttributes' => $filterableAttributes,
            'appliedFilters' => $params->attributeFilters,
            'listingParams' => $params,
            'manufacturerProfileId' => $this->catalog->manufacturerProfileIdForFilters(),
            'searchQuery' => $params->search ?? '',
            'searchTerms' => (new CatalogSearchHighlightService())->searchTerms($params->search),
            'filterDistributors' => Cache::remember(
                $cacheKeyBase.':distributors',
                $cacheTtl,
                fn () => $filterOptions->distributors(
                    $category,
                    $this->productsQueryForFacets($category, $params, ['distributor']),
                ),
            ),
            'filterManufacturers' => Cache::remember(
                $cacheKeyBase.':manufacturers',
                $cacheTtl,
                fn () => $filterOptions->manufacturers(
                    $category,
                    $this->productsQueryForFacets($category, $params, ['manufacturer']),
                ),
            ),
            'priceBounds' => Cache::remember(
                $cacheKeyBase.':priceBounds',
                $cacheTtl,
                fn () => $filterOptions->priceBounds($category),
            ),
            'visibleStructuralFilters' => $this->visibleStructuralFilters(),
            'emptyState' => $emptyState,
        ];
    }

    /**
     * @return list<string>
     */
    public function visibleStructuralFilters(): array
    {
        $role = $this->catalog->catalogRole()?->slug;

        return match ($role) {
            Role::SLUG_MANUFACTURER => ['stock', 'price'],
            Role::SLUG_DISTRIBUTOR => ['manufacturer', 'stock', 'price'],
            Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE => ['distributor', 'manufacturer', 'stock', 'price'],
            default => [],
        };
    }

    /**
     * Запрос товаров для подсчёта facets: все фильтры, кроме перечисленных в $excludeFilters.
     *
     * @param  list<string>  $excludeFilters  distributor|manufacturer|stock|price|search|attributes
     * @return Builder<Product>
     */
    public function productsQueryForFacets(?ProductCategory $category, CatalogListingParams $params, array $excludeFilters = []): Builder
    {
        return $this->applyListingFilters(
            $this->catalog->visibleProductsQuery(),
            $category,
            $this->paramsWithoutFilters($params, $excludeFilters),
        );
    }

    private function paramsWithoutFilters(CatalogListingParams $params, array $excludeFilters): CatalogListingParams
    {
        return new CatalogListingParams(
            search: in_array('search', $excludeFilters, true) ? null : $params->search,
            searchScope: $params->searchScope,
            attributeFilters: in_array('attributes', $excludeFilters, true) ? [] : $params->attributeFilters,
            distributorIds: in_array('distributor', $excludeFilters, true) ? [] : $params->distributorIds,
            manufacturerIds: in_array('manufacturer', $excludeFilters, true) ? [] : $params->manufacturerIds,
            stock: in_array('stock', $excludeFilters, true) ? null : $params->stock,
            priceMin: in_array('price', $excludeFilters, true) ? null : $params->priceMin,
            priceMax: in_array('price', $excludeFilters, true) ? null : $params->priceMax,
            perPage: $params->perPage,
        );
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyListingFilters(Builder $query, ?ProductCategory $category, CatalogListingParams $params): Builder
    {
        if ($category !== null && $params->searchScope !== CatalogListingParams::SEARCH_SCOPE_GLOBAL) {
            $category->loadAncestors();
            $query->inCategory($category->id);
        }

        $query->withAttributeFilters($params->attributeFilters);

        if ($params->manufacturerIds !== []) {
            $query->whereIn('manufacturer_profile_id', $params->manufacturerIds);
        }

        if ($this->catalog->isBuyerSideCatalog()) {
            $offers = $this->catalog->distributorOffers();
            $query = $offers->applyDistributorFilter($query, $params->distributorIds);
            if ($params->stock !== null) {
                $query = $offers->applyStockFilter($query, $params->stock);
            }
            $query = $offers->applyPriceFilter($query, $params->priceMin, $params->priceMax);
        } else {
            if ($params->stock !== null) {
                $query = $this->applyManufacturerStockFilter($query, $params->stock);
            }
            if ($params->priceMin !== null) {
                $query->where('base_price', '>=', $params->priceMin);
            }
            if ($params->priceMax !== null) {
                $query->where('base_price', '<=', $params->priceMax);
            }
        }

        if ($params->search !== null) {
            $query->search($params->search, $this->searchOptions());
        }

        return $query;
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

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyManufacturerStockFilter(Builder $query, string $stock): Builder
    {
        return match ($stock) {
            CatalogListingParams::STOCK_IN_STOCK => $query->withAvailableStock(),
            CatalogListingParams::STOCK_ON_ORDER => $query->withoutAvailableStock(),
            CatalogListingParams::STOCK_OUT_OF_STOCK => $query->withoutAvailableStock(),
            default => $query,
        };
    }
}

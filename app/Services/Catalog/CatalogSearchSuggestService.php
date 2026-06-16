<?php

namespace App\Services\Catalog;

use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class CatalogSearchSuggestService
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly User $user,
    ) {}

    /**
     * @return array{
     *     products: list<array{id: int, name: string, sku: ?string, url: string, image: ?string}>,
     *     categories: list<array{id: int, name: string, slug: string, count: int, url: string}>,
     *     manufacturers: list<array{id: int, name: string, count: int}>,
     *     articles: list<array{sku: string, product_id: int, name: string, url: string}>,
     *     popular: list<array{query: string, count: int}>
     * }
     */
    public function suggest(
        string $query,
        ?ProductCategory $category,
        string $searchScope,
        string $productShowRoute,
        string $catalogIndexRoute,
    ): array {
        $query = trim($query);
        $minQueryLength = CatalogSearchSettings::minQueryLength();
        $suggestLimit = CatalogSearchSettings::suggestLimit();
        if (mb_strlen($query) < $minQueryLength) {
            return array_merge($this->emptyPayload(), [
                'popular' => app(CatalogSearchLogService::class)->popularQueries(
                    $this->catalog->catalogRole()?->slug,
                    $this->catalog->regionId(),
                ),
            ]);
        }

        $baseQuery = $this->catalog->visibleProductsQuery()->search($query, $this->searchOptions());
        if ($category !== null && $searchScope !== CatalogListingParams::SEARCH_SCOPE_GLOBAL) {
            $baseQuery->inCategory($category->id);
        }

        $matchingIds = (clone $baseQuery)->select('products.id');

        $highlight = new CatalogSearchHighlightService();
        $searchTerms = $highlight->searchTerms($query);

        $products = (clone $baseQuery)
            ->with(['images', 'category'])
            ->orderBy('name')
            ->limit($suggestLimit)
            ->get()
            ->map(fn (Product $product): array => $this->mapProduct($product, $productShowRoute, $highlight, $searchTerms))
            ->values()
            ->all();

        $categories = $this->suggestCategories($matchingIds, $catalogIndexRoute, $suggestLimit);
        $manufacturers = $this->suggestManufacturers($matchingIds, $suggestLimit);
        $articles = $this->suggestArticles($query, $baseQuery, $productShowRoute, $highlight, $searchTerms, $suggestLimit);

        return [
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'articles' => $articles,
            'popular' => [],
        ];
    }

    /**
     * @return array{products: array, categories: array, manufacturers: array, articles: array, popular: array}
     */
    private function emptyPayload(): array
    {
        return [
            'products' => [],
            'categories' => [],
            'manufacturers' => [],
            'articles' => [],
            'popular' => [],
        ];
    }

    /**
     * @return array{id: int, name: string, sku: ?string, url: string, image: ?string}
     */
    private function mapProduct(
        Product $product,
        string $showRoute,
        CatalogSearchHighlightService $highlight,
        array $searchTerms,
    ): array {
        $image = $product->primaryImage();

        return [
            'id' => $product->id,
            'name' => $searchTerms !== []
                ? $highlight->highlight($product->name, $searchTerms)
                : e($product->name),
            'sku' => $product->sku,
            'url' => route($showRoute, $product),
            'image' => $image?->url,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $matchingIds
     * @return list<array{id: int, name: string, slug: string, count: int, url: string}>
     */
    private function suggestCategories($matchingIds, string $catalogIndexRoute, int $limit): array
    {
        $rows = Product::query()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->whereIn('products.id', $matchingIds)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $categories = ProductCategory::query()
            ->whereIn('id', $rows->pluck('category_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($categories, $catalogIndexRoute) {
                $category = $categories->get($row->category_id);
                if (! $category) {
                    return null;
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => (int) $row->aggregate,
                    'url' => route($catalogIndexRoute, ['category' => $category->slug]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $matchingIds
     * @return list<array{id: int, name: string, count: int}>
     */
    private function suggestManufacturers($matchingIds, int $limit): array
    {
        if ($this->catalog->catalogRole()?->slug === Role::SLUG_MANUFACTURER) {
            return [];
        }

        $rows = Product::query()
            ->selectRaw('manufacturer_profile_id, COUNT(*) as aggregate')
            ->whereIn('products.id', $matchingIds)
            ->whereNotNull('manufacturer_profile_id')
            ->groupBy('manufacturer_profile_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $profiles = ManufacturerProfile::query()
            ->whereIn('id', $rows->pluck('manufacturer_profile_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($profiles) {
                $profile = $profiles->get($row->manufacturer_profile_id);
                if (! $profile) {
                    return null;
                }

                return [
                    'id' => $profile->id,
                    'name' => $profile->short_name ?: $profile->full_name,
                    'count' => (int) $row->aggregate,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $baseQuery
     * @return list<array{sku: string, product_id: int, name: string, url: string}>
     */
    private function suggestArticles(
        string $query,
        $baseQuery,
        string $showRoute,
        CatalogSearchHighlightService $highlight,
        array $searchTerms,
        int $limit,
    ): array {
        $compact = preg_replace('/\s+/u', '', $query);

        return (clone $baseQuery)
            ->where(function ($q) use ($query, $compact) {
                foreach (['sku', 'manufacturer_sku', 'distributor_sku'] as $field) {
                    $q->orWhere($field, 'like', "{$query}%")
                        ->orWhereRaw(
                            "REPLACE(REPLACE(COALESCE({$field}, ''), ' ', ''), CHAR(9), '') LIKE ?",
                            ["{$compact}%"]
                        );
                }
            })
            ->orderBy('sku')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'manufacturer_sku'])
            ->map(function (Product $product) use ($showRoute) {
                $sku = $product->sku ?: $product->manufacturer_sku;
                if ($sku === null || $sku === '') {
                    return null;
                }

                return [
                    'sku' => $searchTerms !== []
                        ? $highlight->highlight($sku, $searchTerms)
                        : e($sku),
                    'product_id' => $product->id,
                    'name' => $searchTerms !== []
                        ? $highlight->highlight($product->name, $searchTerms)
                        : e($product->name),
                    'url' => route($showRoute, $product),
                ];
            })
            ->filter()
            ->values()
            ->all();
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

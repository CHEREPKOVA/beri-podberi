<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait BuildsCatalogListing
{
    use ParsesCatalogAttributeFilters;

    /**
     * @return array{
     *     products: LengthAwarePaginator,
     *     categoryTree: Collection,
     *     catalogTreeOpenSlugs: array<string, true>,
     *     filterableAttributes: Collection,
     *     appliedFilters: array,
     *     manufacturerProfileId: ?int,
     *     searchQuery: string
     * }
     */
    protected function buildCatalogListing(Request $request, ?ProductCategory $category, CatalogQueryService $catalog): array
    {
        $categoryTree = $catalog->categoryTree();
        $attributeFilters = $this->parseCatalogAttributeFilters($request);
        $filterableAttributes = $category
            ? ProductAttribute::active()->forCategory($category->id)->filterable()->orderedForFilters()->get()
            : collect();

        $query = $catalog->visibleProductsQuery()
            ->with(['category', 'images', 'manufacturerProfile']);

        if ($category) {
            $category->loadAncestors();
            $query->inCategory($category->id);
        }

        $query->withAttributeFilters($attributeFilters);

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $products = $query->orderBy('name')->paginate(24)->withQueryString();
        $collection = $products->getCollection()->map(function ($product) use ($catalog) {
            $product->setAttribute('has_visible_analogs', $catalog->hasVisibleAnalogs($product));

            return $product;
        });
        if ($catalog->isEndCompanyCatalog()) {
            $collection = $catalog->distributorOffers()->enrichProducts($collection);
        }
        $products->setCollection($collection);

        return [
            'products' => $products,
            'categoryTree' => $categoryTree,
            'catalogTreeOpenSlugs' => ProductCategory::initialOpenSlugsForCatalogTree($categoryTree, $category),
            'filterableAttributes' => $filterableAttributes,
            'appliedFilters' => $attributeFilters,
            'manufacturerProfileId' => $catalog->manufacturerProfileIdForFilters(),
            'searchQuery' => $request->filled('search') ? $request->string('search')->toString() : '',
        ];
    }
}

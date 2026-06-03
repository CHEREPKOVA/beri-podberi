<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Concerns\BuildsCatalogListing;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CatalogController extends Controller
{
    use BuildsCatalogListing;

    public function index(Request $request, ?ProductCategory $category = null): View
    {
        $user = $request->user();
        $catalogRole = $user->getCurrentRole();
        if ($category !== null && ! $category->isShownInCatalogForRole($catalogRole)) {
            abort(404);
        }

        $catalog = new CatalogQueryService($user);
        $listing = $this->buildCatalogListing($request, $category, $catalog);

        return view('buyer.catalog.index', array_merge($listing, [
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'companyRegionName' => $user->currentCompanyRegionName(),
            'companyRegionId' => $user->currentCompanyRegionId(),
        ]));
    }

    public function products(Request $request): Response
    {
        $user = $request->user();
        $catalogRole = $user->getCurrentRole();
        $category = $this->resolveCategoryFromRequest($catalogRole, $request->get('category'));
        $catalog = new CatalogQueryService($user);
        $listing = $this->buildCatalogListing($request, $category, $catalog);

        return response()->view('catalog._products', array_merge($listing, [
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'companyRegionName' => $user->currentCompanyRegionName(),
            'catalogIndexRoute' => 'buyer.catalog.index',
            'catalogShowRoute' => 'buyer.catalog.show',
        ]))->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Product $product): View
    {
        $user = $request->user();
        $regionId = $user->currentCompanyRegionId();
        $catalogRole = $user->getCurrentRole();
        $catalog = new CatalogQueryService($user);

        $product->load([
            'manufacturerProfile.regions',
            'availableRegions',
            'category.parent',
            'additionalCategories',
            'images',
            'unitType',
            'attributeValues.attribute',
            'stocks.warehouse.region',
            'analogs',
            'analogOf',
        ]);

        if (! $product->isVisibleInCatalog()) {
            abort(404);
        }
        if (! $product->category->isShownInCatalogForRole($catalogRole)) {
            abort(404);
        }
        if (! $catalog->visibleProductsQuery()->where('products.id', $product->id)->exists()) {
            abort(404);
        }

        $visibleStocks = $product->visibleStocksForRegion($regionId);
        $analogs = $this->resolveVisibleAnalogs($product, $catalog);
        $productUnavailable = $visibleStocks->sum('available_quantity') <= 0;
        $categoryAttributes = $product->attributeValuesVisibleInCategory();
        $distributors = $catalog->distributorsForProductInRegion($product);

        return view('buyer.catalog.show', [
            'product' => $product,
            'categoryAttributes' => $categoryAttributes,
            'visibleStocks' => $visibleStocks,
            'companyRegionName' => $user->currentCompanyRegionName(),
            'companyRegionId' => $regionId,
            'analogs' => $analogs,
            'productUnavailable' => $productUnavailable,
            'currentRoleSlug' => $catalogRole?->slug,
            'distributors' => $distributors,
        ]);
    }

    /**
     * @return Collection<int, Product>
     */
    private function resolveVisibleAnalogs(Product $product, CatalogQueryService $catalog): Collection
    {
        $analogIds = $product->allAnalogIds();
        if ($analogIds === []) {
            return collect();
        }

        return $catalog->visibleProductsQuery()
            ->with([
                'category',
                'images',
                'manufacturerProfile.regions',
                'attributeValues.attribute',
                'stocks.warehouse.region',
                'additionalCategories',
            ])
            ->whereIn('products.id', $analogIds)
            ->where('products.id', '!=', $product->id)
            ->compatibleWithProduct($product)
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $candidate): bool => $candidate->hasEnoughCharacteristicsForAnalog())
            ->values();
    }

    private function resolveCategoryFromRequest(?Role $catalogRole, ?string $value): ?ProductCategory
    {
        if ($value === null || $value === '') {
            return null;
        }

        $category = is_numeric($value)
            ? ProductCategory::active()->find((int) $value)
            : ProductCategory::active()->where('slug', $value)->first();

        if ($category && ! $category->isShownInCatalogForRole($catalogRole)) {
            return null;
        }

        return $category;
    }
}

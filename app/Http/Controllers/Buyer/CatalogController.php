<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Concerns\BuildsCatalogListing;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\ProductCatalogCardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'documents',
        ]);

        if (! $product->isVisibleInCatalog()) {
            abort(404);
        }
        if (! $product->category->isShownInCatalogForRole($catalogRole)) {
            abort(404);
        }

        $inCatalog = $catalog->visibleProductsQuery()->where('products.id', $product->id)->exists();
        if (! $inCatalog) {
            abort(404);
        }

        $card = new ProductCatalogCardService($user, $catalog);
        $cardData = $card->build($product);

        $offerSummary = $cardData['offerSummary'];
        $product->setAttribute('unavailable_in_region', $offerSummary['unavailable_in_region'] ?? false);
        $product->setAttribute('is_purchasable', $offerSummary['is_purchasable'] ?? false);

        return view('buyer.catalog.show', array_merge($cardData, [
            'product' => $product,
            'backUrl' => route('buyer.catalog.index', ['category' => $product->category?->slug]),
            'analogShowRoute' => 'buyer.catalog.show',
            'showActions' => $cardData['cardRole'] === 'distributor',
            'liveUrl' => route('buyer.catalog.product.live', $product),
        ]));
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

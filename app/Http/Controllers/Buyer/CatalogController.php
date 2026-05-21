<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, ?ProductCategory $category = null): View
    {
        $user = $request->user();
        $regionId = $user->currentCompanyRegionId();
        $catalogRole = $user->getCurrentRole();
        if ($category !== null && ! $category->isShownInCatalogForRole($catalogRole)) {
            abort(404);
        }

        $categoryTree = ProductCategory::getTree(true, null, $catalogRole);
        $query = Product::query()
            ->with(['category', 'images', 'manufacturerProfile', 'stocks.warehouse.region'])
            ->withCount(['analogs', 'analogOf'])
            ->published();

        if ($category) {
            $query->inCategory($category->id);
        }

        $query->availableInRegion($regionId);

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $products = $query->orderBy('name')->paginate(24)->withQueryString();

        return view('buyer.catalog.index', [
            'categoryTree' => $categoryTree,
            'products' => $products,
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'companyRegionName' => $user->currentCompanyRegionName(),
            'companyRegionId' => $regionId,
        ]);
    }

    public function products(Request $request): Response
    {
        $user = $request->user();
        $regionId = $user->currentCompanyRegionId();
        $catalogRole = $user->getCurrentRole();
        $category = $this->resolveCategoryFromRequest($catalogRole, $request->get('category'));

        $query = Product::query()
            ->with(['category', 'images', 'manufacturerProfile', 'stocks.warehouse.region'])
            ->withCount(['analogs', 'analogOf'])
            ->published()
            ->availableInRegion($regionId);

        if ($category) {
            $query->inCategory($category->id);
        }

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $products = $query->orderBy('name')->paginate(24)->withQueryString();

        return response()->view('buyer.catalog._products', [
            'products' => $products,
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'companyRegionName' => $user->currentCompanyRegionName(),
            'companyRegionId' => $regionId,
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, Product $product): View
    {
        $user = $request->user();
        $regionId = $user->currentCompanyRegionId();
        $catalogRole = $user->getCurrentRole();

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

        if (! $product->show_in_catalog || $product->status !== Product::STATUS_ACTIVE) {
            abort(404);
        }
        if ($product->category && ! $product->category->isShownInCatalogForRole($catalogRole)) {
            abort(404);
        }
        if ($regionId !== null) {
            $allowedByProduct = $product->availableRegions->isEmpty()
                || $product->availableRegions->contains('id', $regionId);
            $allowedByManufacturer = $product->manufacturerProfile?->regions?->contains('id', $regionId) ?? false;
            if (! $allowedByProduct || ! $allowedByManufacturer) {
                abort(404);
            }
        }

        $visibleStocks = $product->visibleStocksForRegion($regionId);
        $currentRoleSlug = $catalogRole?->slug;
        $analogs = $this->resolveVisibleAnalogs($product, $regionId, $currentRoleSlug);
        $productUnavailable = $visibleStocks->sum('available_quantity') <= 0;

        return view('buyer.catalog.show', [
            'product' => $product,
            'visibleStocks' => $visibleStocks,
            'companyRegionName' => $user->currentCompanyRegionName(),
            'companyRegionId' => $regionId,
            'analogs' => $analogs,
            'productUnavailable' => $productUnavailable,
            'currentRoleSlug' => $currentRoleSlug,
        ]);
    }

    /**
     * @return Collection<int, Product>
     */
    private function resolveVisibleAnalogs(Product $product, ?int $regionId, ?string $currentRoleSlug): Collection
    {
        $analogIds = $product->allAnalogIds();
        if ($analogIds === []) {
            return collect();
        }

        $query = Product::query()
            ->with([
                'category',
                'images',
                'manufacturerProfile.regions',
                'attributeValues.attribute',
                'stocks.warehouse.region',
                'additionalCategories',
            ])
            ->whereIn('id', $analogIds)
            ->where('id', '!=', $product->id)
            ->published()
            ->compatibleWithProduct($product);

        if (in_array($currentRoleSlug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true)) {
            $query->availableInRegion($regionId);
        } elseif ($currentRoleSlug === Role::SLUG_DISTRIBUTOR) {
            $query->forRegion($regionId);
        } else {
            $query->availableInRegion($regionId);
        }

        return $query
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

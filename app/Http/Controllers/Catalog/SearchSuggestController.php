<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Concerns\ResolvesCatalogContext;
use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Services\Catalog\CatalogListingParams;
use App\Services\Catalog\CatalogSearchSuggestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestController extends Controller
{
    use ResolvesCatalogContext;

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $catalogRole = $request->user()->getCurrentRole();
        $category = $this->resolveCategory($catalogRole, $request->get('category'));
        $searchScope = $request->string('search_scope', CatalogListingParams::SEARCH_SCOPE_CATEGORY)->toString();
        if (! in_array($searchScope, [CatalogListingParams::SEARCH_SCOPE_CATEGORY, CatalogListingParams::SEARCH_SCOPE_GLOBAL], true)) {
            $searchScope = CatalogListingParams::SEARCH_SCOPE_CATEGORY;
        }

        [$showRoute, $indexRoute] = $this->routesForRole($catalogRole?->slug);

        $catalog = $this->makeCatalogQueryService($request);
        $suggest = new CatalogSearchSuggestService($catalog, $request->user());

        return response()->json($suggest->suggest(
            $query,
            $category,
            $searchScope,
            $showRoute,
            $indexRoute,
        ));
    }

    private function resolveCategory(?Role $catalogRole, mixed $value): ?ProductCategory
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

    /**
     * @return array{0: string, 1: string}
     */
    private function routesForRole(?string $roleSlug): array
    {
        if ($roleSlug === Role::SLUG_MANUFACTURER) {
            return ['manufacturer.catalog.show', 'manufacturer.catalog.index'];
        }

        return ['buyer.catalog.show', 'buyer.catalog.index'];
    }
}

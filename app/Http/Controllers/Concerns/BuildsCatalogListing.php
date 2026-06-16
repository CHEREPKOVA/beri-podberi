<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ProductCategory;
use App\Services\Catalog\CatalogListingParams;
use App\Services\Catalog\CatalogListingService;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Http\Request;

trait BuildsCatalogListing
{
    use ParsesCatalogListingParams;
    use ResolvesCatalogContext;

    protected function buildCatalogListing(Request $request, ?ProductCategory $category, CatalogQueryService $catalog, string $catalogIndexRoute = 'manufacturer.catalog.index'): array
    {
        $params = $this->parseCatalogListingParams($request);
        $listing = new CatalogListingService($catalog, $request->user());

        return $listing->build($category, $params, $catalogIndexRoute);
    }
}

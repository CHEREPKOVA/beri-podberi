<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\CatalogRegionService;
use Illuminate\Http\Request;

trait ResolvesCatalogContext
{
    protected function catalogRegionService(): CatalogRegionService
    {
        return app(CatalogRegionService::class);
    }

    protected function makeCatalogQueryService(Request $request): CatalogQueryService
    {
        $user = $request->user();
        $regionId = $this->catalogRegionService()->resolveRegionId($user);

        return new CatalogQueryService($user, $regionId);
    }
}

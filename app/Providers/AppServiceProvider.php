<?php

namespace App\Providers;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Catalog\CatalogCacheService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $invalidateCatalogCache = static fn () => app(CatalogCacheService::class)->bump();

        foreach ([
            Product::class,
            DistributorProduct::class,
            DistributorProductStock::class,
            ManufacturerDistributorPartnership::class,
            ProductCategory::class,
        ] as $modelClass) {
            $modelClass::saved($invalidateCatalogCache);
            $modelClass::deleted($invalidateCatalogCache);

            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $modelClass::restored($invalidateCatalogCache);
            }
        }
    }
}

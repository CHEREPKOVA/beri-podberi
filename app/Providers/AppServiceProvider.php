<?php

namespace App\Providers;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Services\Cart\CartService;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Order\OrderStatusWorkflowService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view): void {
            $cartCount = 0;
            $purchaseCartCount = 0;
            $ordersAttentionCount = 0;
            $purchasesAttentionCount = 0;
            $user = auth()->user();
            $roleSlug = $user?->getCurrentRole()?->slug;
            $workflow = app(OrderStatusWorkflowService::class);

            if (
                $user
                && in_array($roleSlug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true)
            ) {
                $cartCount = app(CartService::class)->itemsCount($user);
                $endCompanyProfileId = $user->endCompanyProfile?->id;
                if ($endCompanyProfileId !== null) {
                    $ordersAttentionCount = $workflow->countRequiringAttentionForBuyer($endCompanyProfileId);
                }
            }

            if ($user && $roleSlug === Role::SLUG_DISTRIBUTOR) {
                $profileId = $user->distributorProfile?->id;
                if ($profileId !== null) {
                    $ordersAttentionCount = $workflow->countRequiringAttentionForDistributor($profileId);
                    $purchasesAttentionCount = $workflow->countRequiringAttentionForDistributorPurchases($profileId);
                }
                $purchaseCartCount = app(\App\Services\Cart\DistributorPurchaseCartService::class)->itemsCount($user);
            }

            if ($user && $roleSlug === Role::SLUG_MANUFACTURER) {
                $profileId = $user->manufacturerProfile?->id;
                if ($profileId !== null) {
                    $ordersAttentionCount = $workflow->countRequiringAttentionForManufacturer($profileId);
                }
            }

            $view->with([
                'buyerCartItemsCount' => $cartCount,
                'purchaseCartItemsCount' => $purchaseCartCount,
                'sidebarCartItemsCount' => $roleSlug === Role::SLUG_DISTRIBUTOR ? $purchaseCartCount : $cartCount,
                'ordersAttentionCount' => $ordersAttentionCount,
                'purchasesAttentionCount' => $purchasesAttentionCount,
                // обратная совместимость для шаблона
                'distributorNewOrdersCount' => $ordersAttentionCount,
            ]);
        });

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

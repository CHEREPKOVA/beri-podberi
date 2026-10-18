<?php

namespace App\Providers;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Services\Cart\CartService;
use App\Services\Cart\DistributorPurchaseCartService;
use App\Services\Catalog\CatalogCacheService;
use App\Services\DistributorStaffAccess;
use App\Services\EndCompanyStaffAccess;
use App\Services\ManufacturerStaffAccess;
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
            $manufacturerStaffAccess = [
                'is_staff' => false,
                'is_admin' => false,
                'can_employees' => false,
                'can_profile' => false,
                'can_warehouses' => false,
                'can_products' => false,
                'can_orders' => false,
                'can_partners' => false,
                'can_catalog' => false,
                'can_discounts' => false,
            ];
            $distributorStaffAccess = [
                'is_staff' => false,
                'is_admin' => false,
                'can_employees' => false,
                'can_profile' => false,
                'can_warehouses' => false,
                'can_products' => false,
                'can_orders' => false,
                'can_purchases' => false,
                'can_catalog' => false,
                'can_discounts' => false,
            ];
            $endCompanyStaffAccess = [
                'is_staff' => false,
                'is_admin' => false,
                'can_employees' => false,
                'can_profile' => false,
                'can_catalog' => false,
                'can_cart' => false,
                'can_orders' => false,
            ];
            $user = auth()->user();
            $roleSlug = $user?->getCurrentRole()?->slug;
            $workflow = app(OrderStatusWorkflowService::class);

            if ($user) {
                $manufacturerStaffAccess = app(ManufacturerStaffAccess::class)->sidebarState($user);
                $distributorStaffAccess = app(DistributorStaffAccess::class)->sidebarState($user);
                $endCompanyStaffAccess = app(EndCompanyStaffAccess::class)->sidebarState($user);
            }

            if (
                $user
                && in_array($roleSlug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true)
                && ! ($manufacturerStaffAccess['is_staff'] ?? false)
                && ! ($distributorStaffAccess['is_staff'] ?? false)
            ) {
                $cartCount = ($endCompanyStaffAccess['can_cart'] ?? false)
                    ? app(CartService::class)->itemsCount($user)
                    : 0;
                $endCompanyProfileId = $user->currentEndCompanyProfile()?->id;
                if ($endCompanyProfileId !== null && ($endCompanyStaffAccess['can_orders'] ?? false)) {
                    $ordersAttentionCount = $workflow->countRequiringAttentionForBuyer($endCompanyProfileId);
                }
            }

            if ($user && ($roleSlug === Role::SLUG_DISTRIBUTOR || ($distributorStaffAccess['is_staff'] ?? false))) {
                $profileId = $user->currentDistributorProfile()?->id;
                if ($profileId !== null) {
                    $ordersAttentionCount = $workflow->countRequiringAttentionForDistributor($profileId);
                    $purchasesAttentionCount = $workflow->countRequiringAttentionForDistributorPurchases($profileId);
                }
                $purchaseCartCount = app(DistributorPurchaseCartService::class)->itemsCount($user);
            }

            if ($user && ($roleSlug === Role::SLUG_MANUFACTURER || ($manufacturerStaffAccess['is_staff'] ?? false))) {
                $profileId = $user->currentManufacturerProfile()?->id;
                if ($profileId !== null) {
                    $ordersAttentionCount = $workflow->countRequiringAttentionForManufacturer($profileId);
                }
            }

            $view->with([
                'buyerCartItemsCount' => $cartCount,
                'purchaseCartItemsCount' => $purchaseCartCount,
                'sidebarCartItemsCount' => ($distributorStaffAccess['is_staff'] ?? false) ? $purchaseCartCount : $cartCount,
                'ordersAttentionCount' => $ordersAttentionCount,
                'purchasesAttentionCount' => $purchasesAttentionCount,
                'manufacturerStaffAccess' => $manufacturerStaffAccess,
                'distributorStaffAccess' => $distributorStaffAccess,
                'endCompanyStaffAccess' => $endCompanyStaffAccess,
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

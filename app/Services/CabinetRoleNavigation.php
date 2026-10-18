<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CabinetRoleNavigation
{
    public function __construct(
        private readonly ManufacturerStaffAccess $manufacturerAccess,
        private readonly DistributorStaffAccess $distributorAccess,
        private readonly EndCompanyStaffAccess $endCompanyAccess,
    ) {}

    /**
     * Адрес того же раздела в кабинете новой роли.
     * Если раздела у роли нет, остаётся главная кабинета.
     */
    public function urlAfterSwitch(User $user, ?string $fromUrl, string $host): string
    {
        $section = $this->sectionFromRoute($this->routeNameFromUrl($fromUrl, $host));

        return $this->urlFor($user, $section);
    }

    public function urlFor(User $user, ?string $section): string
    {
        $home = route('dashboard');
        $cabinet = $this->cabinetOf($user);
        if ($section === null || $section === 'dashboard' || $cabinet === null) {
            return $home;
        }

        $routes = $this->routesFor($cabinet);
        if (! isset($routes[$section]) || ! $this->allows($user, $cabinet, $section)) {
            return $home;
        }

        return $routes[$section];
    }

    private function cabinetOf(User $user): ?string
    {
        $slug = $user->getCurrentRole()?->slug;

        if (in_array($slug, [Role::SLUG_ADMIN, Role::SLUG_MANAGER, Role::SLUG_ANALYST], true)) {
            return 'admin';
        }

        if ($slug === Role::SLUG_MANUFACTURER) {
            return 'manufacturer';
        }

        if ($slug === Role::SLUG_DISTRIBUTOR) {
            return 'distributor';
        }

        if ($slug === Role::SLUG_END_COMPANY) {
            return 'end_company';
        }

        if ($slug === Role::SLUG_COMPANY_EMPLOYEE) {
            if ($user->isManufacturerStaff()) {
                return 'manufacturer';
            }
            if ($user->isDistributorStaff()) {
                return 'distributor';
            }
            if ($user->isEndCompanyStaff()) {
                return 'end_company';
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function routesFor(string $cabinet): array
    {
        return match ($cabinet) {
            'manufacturer' => [
                'profile' => route('manufacturer.profile'),
                'employees' => route('manufacturer.employees.index'),
                'warehouses' => route('manufacturer.warehouses.index'),
                'products' => route('manufacturer.products.index'),
                'orders' => route('manufacturer.orders.index'),
                'discounts' => route('manufacturer.discounts.index'),
                'catalog' => route('manufacturer.catalog.index'),
                'partners' => route('manufacturer.partners.index'),
            ],
            'distributor' => [
                'profile' => route('distributor.profile'),
                'employees' => route('distributor.employees.index'),
                'warehouses' => route('distributor.warehouses.index'),
                'products' => route('distributor.products.index'),
                'orders' => route('distributor.orders.index'),
                'discounts' => route('distributor.discounts.index'),
                'purchases' => route('distributor.purchases.index'),
                'cart' => route('distributor.purchases.cart.index'),
                'catalog' => route('buyer.catalog.index'),
            ],
            'end_company' => [
                'profile' => route('end_company.profile'),
                'employees' => route('end_company.employees.index'),
                'catalog' => route('buyer.catalog.index'),
                'cart' => route('buyer.cart.index'),
                'orders' => route('buyer.orders.index'),
            ],
            'admin' => [
                'orders' => route('admin.orders.index'),
                'catalog' => route('admin.catalog.index'),
            ],
            default => [],
        };
    }

    private function allows(User $user, string $cabinet, string $section): bool
    {
        if ($cabinet === 'admin') {
            return match ($section) {
                'orders' => $user->hasPermission('orders.manage'),
                'catalog' => $user->hasPermission('catalog.manage'),
                default => false,
            };
        }

        $access = match ($cabinet) {
            'manufacturer' => $this->manufacturerAccess,
            'distributor' => $this->distributorAccess,
            'end_company' => $this->endCompanyAccess,
            default => null,
        };

        if ($access === null || ! $access->canAccessCabinet($user)) {
            return false;
        }

        $ability = match ($section) {
            'cart' => $cabinet === 'end_company' ? 'cart' : 'purchases',
            'purchases' => 'purchases',
            default => $section,
        };

        return $access->canAccessSection($user, $ability);
    }

    private function routeNameFromUrl(?string $fromUrl, string $host): ?string
    {
        $path = $this->sameHostPath($fromUrl, $host);
        if ($path === null) {
            return null;
        }

        try {
            return Route::getRoutes()->match(Request::create($path))->getName();
        } catch (NotFoundHttpException) {
            return null;
        }
    }

    private function sameHostPath(?string $fromUrl, string $host): ?string
    {
        $fromUrl = trim((string) $fromUrl);
        if ($fromUrl === '') {
            return null;
        }

        $parts = parse_url($fromUrl);
        if ($parts === false) {
            return null;
        }

        if (isset($parts['host']) && strcasecmp((string) $parts['host'], $host) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }

    private function sectionFromRoute(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        if ($name === 'dashboard') {
            return 'dashboard';
        }

        if (
            str_starts_with($name, 'distributor.purchases.cart')
            || str_starts_with($name, 'distributor.purchases.checkout')
        ) {
            return 'cart';
        }

        if (str_starts_with($name, 'distributor.purchases')) {
            return 'purchases';
        }

        if (str_starts_with($name, 'buyer.cart') || str_starts_with($name, 'buyer.checkout')) {
            return 'cart';
        }

        if (str_contains($name, 'promo-codes') || str_contains($name, 'promo_codes')) {
            return 'discounts';
        }

        foreach ([
            'profile' => '.profile',
            'employees' => '.employees',
            'warehouses' => '.warehouses',
            'products' => '.products',
            'discounts' => '.discounts',
            'catalog' => '.catalog',
            'partners' => '.partners',
            'orders' => '.orders',
        ] as $section => $needle) {
            if (str_contains($name, $needle)) {
                return $section;
            }
        }

        return null;
    }
}

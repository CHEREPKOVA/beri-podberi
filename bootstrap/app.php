<?php

use App\Http\Middleware\EnsureBuyerCabinetAccess;
use App\Http\Middleware\EnsureCurrentRoleSelected;
use App\Http\Middleware\EnsureDistributorCabinetAccess;
use App\Http\Middleware\EnsureEndCompanyCabinetAccess;
use App\Http\Middleware\EnsureManufacturerCabinetAccess;
use App\Http\Middleware\EnsureManufacturerPartnerCatalogAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogAdminAction;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        // За reverse-proxy / балансировщиком хостинга X-Forwarded-* нужны для корректного HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role.selected' => EnsureCurrentRoleSelected::class,
            'role' => RequireRole::class,
            'permission' => RequirePermission::class,
            'admin.audit' => LogAdminAction::class,
            'user.active' => EnsureUserIsActive::class,
            'manufacturer.partner' => EnsureManufacturerPartnerCatalogAccess::class,
            'manufacturer.cabinet' => EnsureManufacturerCabinetAccess::class,
            'distributor.cabinet' => EnsureDistributorCabinetAccess::class,
            'end_company.cabinet' => EnsureEndCompanyCabinetAccess::class,
            'buyer.cabinet' => EnsureBuyerCabinetAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

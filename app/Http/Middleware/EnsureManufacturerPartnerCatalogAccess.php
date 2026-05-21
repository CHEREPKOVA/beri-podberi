<?php

namespace App\Http\Middleware;

use App\Services\ManufacturerStaffPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManufacturerPartnerCatalogAccess
{
    public function __construct(
        private readonly ManufacturerStaffPermissions $permissions,
    ) {}

    public function handle(Request $request, Closure $next, ?string $ability = 'view'): Response
    {
        $user = $request->user();

        if (! $user || ! $this->permissions->can($user, $ability)) {
            abort(403, 'Недостаточно прав для доступа к каталогу партнёров.');
        }

        return $next($request);
    }
}

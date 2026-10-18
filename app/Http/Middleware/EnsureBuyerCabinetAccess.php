<?php

namespace App\Http\Middleware;

use App\Services\DistributorStaffAccess;
use App\Services\EndCompanyStaffAccess;
use App\Services\ManufacturerCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerCabinetAccess
{
    public function __construct(
        private readonly ManufacturerCompanyContext $manufacturerContext,
        private readonly DistributorStaffAccess $distributorAccess,
        private readonly EndCompanyStaffAccess $endCompanyAccess,
    ) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();
        if ($user && $this->manufacturerContext->isManufacturerEmployee($user)) {
            abort(403, 'Недостаточно прав доступа.');
        }

        if ($user && $this->distributorAccess->canAccessCabinet($user)) {
            if ($mode === 'end_company') {
                abort(403, 'Недостаточно прав доступа.');
            }

            if (! $this->distributorAccess->canAccessSection($user, 'catalog')) {
                abort(403, 'Недостаточно прав для доступа к этому разделу.');
            }
        }

        if ($user && $this->endCompanyAccess->canAccessCabinet($user) && ! $this->distributorAccess->canAccessCabinet($user)) {
            if (! $this->endCompanyAccess->canAccessSection($user, 'catalog')) {
                abort(403, 'Недостаточно прав для доступа к этому разделу.');
            }
        }

        return $next($request);
    }
}

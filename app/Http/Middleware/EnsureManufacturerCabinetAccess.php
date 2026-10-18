<?php

namespace App\Http\Middleware;

use App\Services\ManufacturerCompanyContext;
use App\Services\ManufacturerStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManufacturerCabinetAccess
{
    public function __construct(
        private readonly ManufacturerCompanyContext $context,
        private readonly ManufacturerStaffAccess $access,
    ) {}

    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->access->canAccessCabinet($user)) {
            abort(403, 'Этот раздел недоступен для вашего профиля.');
        }

        $profile = $this->context->profile($user);
        if ($profile) {
            $user->setRelation('manufacturerProfile', $profile);
        }

        if ($ability !== null && $ability !== '' && ! $this->access->canAccessSection($user, $ability)) {
            abort(403, 'Недостаточно прав для доступа к этому разделу.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\EndCompanyCompanyContext;
use App\Services\EndCompanyStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEndCompanyCabinetAccess
{
    public function __construct(
        private readonly EndCompanyCompanyContext $context,
        private readonly EndCompanyStaffAccess $access,
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
            $user->setRelation('endCompanyProfile', $profile);
        }

        if ($ability !== null && $ability !== '' && ! $this->access->canAccessSection($user, $ability)) {
            abort(403, 'Недостаточно прав для доступа к этому разделу.');
        }

        return $next($request);
    }
}

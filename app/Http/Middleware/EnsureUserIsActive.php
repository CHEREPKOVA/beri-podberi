<?php

namespace App\Http\Middleware;

use App\Services\CurrentRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заблокированные пользователи не могут пользоваться системой (сессия сбрасывается).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('auth.blocked'),
            ]);
        }

        if ($user !== null && ! $request->session()->has(CurrentRoleService::SESSION_KEY)) {
            app(CurrentRoleService::class)->setSingleRoleIfOnlyOne($user);
        }

        return $next($request);
    }
}

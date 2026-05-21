<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Проверка: у пользователя должно быть хотя бы одно из переданных прав.
     *
     * @param  array<int, string>  $permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($permissions === []) {
            return $next($request);
        }

        if (! $user->hasAnyPermission($permissions)) {
            abort(403, 'Недостаточно прав для выполнения действия.');
        }

        return $next($request);
    }
}

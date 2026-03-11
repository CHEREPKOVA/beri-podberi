<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Проверка: у пользователя должна быть одна из переданных ролей.
     * По умолчанию проверяется и текущая выбранная роль (для переключаемых профилей).
     *
     * @param  array<int, string>  $roles  Слаги ролей, например ['admin', 'manager']
     * @param  bool  $requireCurrent  Требовать, чтобы текущая роль в сессии была из списка
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'Недостаточно прав доступа.');
        }

        $current = $user->getCurrentRole();
        if ($current !== null && ! in_array($current->slug, $roles, true)) {
            abort(403, 'Выберите подходящий профиль для доступа к этому разделу.');
        }

        return $next($request);
    }
}

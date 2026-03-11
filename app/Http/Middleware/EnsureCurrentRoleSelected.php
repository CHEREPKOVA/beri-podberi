<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentRoleSelected
{
    /**
     * При нескольких ролях и не выбранной текущей — пропускаем запрос дальше.
     * Модальное окно выбора роли отображается в layout после авторизации.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentRoleSelected
{
    /**
     * Если текущая роль не выбрана, отправляем пользователя на страницу выбора роли.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->needsRoleSelection()) {
            Log::info('EnsureCurrentRoleSelected: redirect to role.select', [
                'url' => $request->fullUrl(),
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
                'has_current_role_id' => $request->session()->has('current_role_id'),
            ]);

            return redirect()->route('role.select');
        }

        return $next($request);
    }
}

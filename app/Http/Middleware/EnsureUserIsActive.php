<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заблокированные пользователи не могут пользоваться системой (сессия сбрасывается).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isProtectedArea = $request->is('admin/*') || $request->is('manufacturer/*');

        if ($isProtectedArea) {
            Log::info('EnsureUserIsActive: start', [
                'url' => $request->fullUrl(),
                'user_id' => $user?->id,
                'auth_check' => Auth::check(),
                'via_remember' => Auth::viaRemember(),
                'session_id' => $request->session()->getId(),
                'has_current_role_id' => $request->session()->has('current_role_id'),
            ]);
        }

        if ($user !== null && $isProtectedArea && ! $request->session()->has('current_role_id')) {
            Log::warning('EnsureUserIsActive: force relogin due to missing current_role_id', [
                'url' => $request->fullUrl(),
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Сессия истекла. Войдите снова.',
            ]);
        }

        // Если сессия потеряна и пользователь восстановлен только через remember-cookie,
        // требуем повторную авторизацию вместо доступа в кабинет в "полуавторизованном" состоянии.
        if ($user !== null && Auth::viaRemember()) {
            Log::warning('EnsureUserIsActive: force relogin due to viaRemember', [
                'url' => $request->fullUrl(),
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Сессия истекла. Войдите снова.',
            ]);
        }

        if ($user !== null && ! $user->is_active) {
            Log::warning('EnsureUserIsActive: blocked user logout', [
                'url' => $request->fullUrl(),
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('auth.blocked'),
            ]);
        }

        if ($isProtectedArea) {
            Log::info('EnsureUserIsActive: pass', [
                'url' => $request->fullUrl(),
                'user_id' => $user?->id,
                'session_id' => $request->session()->getId(),
            ]);
        }

        return $next($request);
    }
}

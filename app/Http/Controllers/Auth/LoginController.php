<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\CurrentRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Обработка входа: проверка учётных данных, создание сессии, редирект.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'auth' => __('auth.failed', [], 'ru'),
            ]);
        }

        $maxFailedLogins = (int) SystemSetting::getActiveParsed('limits.max_failed_logins', 5);
        $throttlingEnabled = $maxFailedLogins > 0;
        $throttleKey = Str::lower('login:'.$request->ip());

        if ($throttlingEnabled && RateLimiter::tooManyAttempts($throttleKey, $maxFailedLogins)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'auth' => __('auth.throttle', ['seconds' => $seconds], 'ru'),
                'throttle_seconds' => (string) $seconds,
            ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($request->only('email', 'password'), $remember)) {
            if ($throttlingEnabled) {
                RateLimiter::hit($throttleKey, 900);
            }

            throw ValidationException::withMessages([
                'auth' => __('auth.failed', [], 'ru'),
            ]);
        }

        if ($throttlingEnabled) {
            RateLimiter::clear($throttleKey);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'auth' => __('auth.blocked', [], 'ru'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ])->save();

        $redirect = app(CurrentRoleService::class)->applyAfterLogin($user);
        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()->intended(route('dashboard'));
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CurrentRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Обработка входа: проверка учётных данных, создание сессии, редирект.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($request->only('email', 'password'), $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed', [], 'ru'),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        $redirect = app(CurrentRoleService::class)->applyAfterLogin($user);
        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()->intended(route('dashboard'));
    }
}

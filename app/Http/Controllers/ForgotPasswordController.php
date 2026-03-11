<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /**
     * Показать форму «Забыли пароль».
     */
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Отправить ссылку для сброса пароля на email.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Ссылка для сброса пароля отправлена на указанный email.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    /**
     * Показать форму ввода нового пароля (по токену из письма).
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password-form', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Сохранить новый пароль.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Пароль успешно изменён. Войдите с новым паролем.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}

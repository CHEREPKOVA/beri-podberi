<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    private const PASSWORD_HISTORY_LIMIT = 5;

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
        $user = User::where('email', $request->input('email'))->first();

        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)->letters()->numbers(),
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (! $user instanceof User || ! is_string($value)) {
                        return;
                    }

                    if (Hash::check($value, $user->password)) {
                        $fail(__('auth.password_reused'));

                        return;
                    }

                    $oldHashes = DB::table('user_password_histories')
                        ->where('user_id', $user->id)
                        ->orderByDesc('created_at')
                        ->limit(self::PASSWORD_HISTORY_LIMIT)
                        ->pluck('password_hash');

                    foreach ($oldHashes as $oldHash) {
                        if (is_string($oldHash) && Hash::check($value, $oldHash)) {
                            $fail(__('auth.password_reused'));

                            return;
                        }
                    }
                },
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                if (! $user instanceof User) {
                    return;
                }

                $user->forceFill([
                    'password' => $password,
                ])->save();

                DB::table('user_password_histories')->insert([
                    'user_id' => $user->id,
                    'password_hash' => $user->password,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $historyIdsToKeep = DB::table('user_password_histories')
                    ->where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->limit(self::PASSWORD_HISTORY_LIMIT)
                    ->pluck('id');

                DB::table('user_password_histories')
                    ->where('user_id', $user->id)
                    ->whereNotIn('id', $historyIdsToKeep)
                    ->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Пароль успешно изменён. Войдите с новым паролем.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}

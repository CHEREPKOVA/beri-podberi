<?php

namespace App\Http\Controllers;

use App\Services\CurrentRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleSelectionController extends Controller
{
    /**
     * Показать экран выбора роли (при нескольких ролях у пользователя).
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->needsRoleSelection()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.role-select', [
            'roles' => $user->roles,
        ]);
    }

    /**
     * Установить выбранную роль и перенаправить в кабинет.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $ok = app(CurrentRoleService::class)->set($user, (int) $request->role_id);
        if (! $ok) {
            return back()->withErrors(['role_id' => 'Выберите одну из ваших ролей.']);
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Смена текущей роли из раздела «Профиль».
     */
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $ok = app(CurrentRoleService::class)->set($user, (int) $request->role_id);
        if (! $ok) {
            return back()->withErrors(['role_id' => 'Выберите одну из ваших ролей.']);
        }

        return back()->with('status', 'Роль успешно изменена.');
    }
}

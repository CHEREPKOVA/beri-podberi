<?php

namespace App\Http\Controllers;

use App\Services\CabinetRoleNavigation;
use App\Services\CurrentRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleSelectionController extends Controller
{
    /**
     * Показать выбор роли в блоке авторизации (при нескольких ролях у пользователя).
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

        return view('auth.login', [
            'roles' => $user->activeRoles(),
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

        return redirect()->route('dashboard');
    }

    /**
     * Смена роли из шапки кабинета. Пользователь остаётся в том же разделе новой роли.
     */
    public function switch(Request $request): JsonResponse|RedirectResponse
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
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Выберите одну из ваших ролей.',
                    'errors' => ['role_id' => ['Выберите одну из ваших ролей.']],
                ], 422);
            }

            return back()->withErrors(['role_id' => 'Выберите одну из ваших ролей.']);
        }

        $from = $request->headers->get('X-Cabinet-From') ?: $request->headers->get('referer');
        $url = app(CabinetRoleNavigation::class)->urlAfterSwitch($user, $from, $request->getHost());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $url,
                'message' => 'Роль успешно изменена.',
            ]);
        }

        return redirect()->to($url)->with('status', 'Роль успешно изменена.');
    }
}

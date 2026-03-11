<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class CurrentRoleService
{
    public const SESSION_KEY = 'current_role_id';

    public function set(User $user, int $roleId): bool
    {
        $role = Role::find($roleId);
        if (! $role || ! $user->roles->contains($role)) {
            return false;
        }
        Session::put(self::SESSION_KEY, $roleId);
        return true;
    }

    public function get(User $user): ?Role
    {
        $roleId = Session::get(self::SESSION_KEY);
        if ($roleId === null) {
            return null;
        }
        $role = Role::find($roleId);
        if (! $role || ! $user->roles->contains($role)) {
            $this->clear();
            return null;
        }
        return $role;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Если у пользователя одна роль — автоматически установить её и вернуть true.
     * Если несколько — не устанавливать, вернуть false.
     */
    public function setSingleRoleIfOnlyOne(User $user): bool
    {
        $roles = $user->roles;
        if ($roles->count() === 1) {
            Session::put(self::SESSION_KEY, $roles->first()->id);
            return true;
        }
        return false;
    }

    /**
     * Нужно ли показать экран выбора роли (несколько ролей и текущая не выбрана).
     */
    public function needsRoleSelection(User $user): bool
    {
        if ($user->roles->count() <= 1) {
            return false;
        }
        return $this->get($user) === null;
    }

    /**
     * Вызвать после успешного входа: установить единственную роль при одной роли.
     * При нескольких ролях редирект не делаем — на странице покажется модальное окно выбора.
     */
    public function applyAfterLogin(User $user): ?RedirectResponse
    {
        $this->setSingleRoleIfOnlyOne($user);
        return null;
    }
}

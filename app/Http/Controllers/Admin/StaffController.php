<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffController extends Controller
{
    /** Роли, которые считаются «сотрудниками платформы» (админ + менеджер). */
    private const STAFF_ROLE_SLUGS = [Role::SLUG_ADMIN, Role::SLUG_MANAGER];

    /** ID главного администратора — редактирование и удаление запрещены. */
    public const PROTECTED_ADMIN_ID = 1;

    /**
     * Список администраторов и менеджеров платформы.
     */
    public function index(Request $request): View
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', self::STAFF_ROLE_SLUGS))
            ->with('roles');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->role));
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'blocked') {
            $query->where('is_active', false);
        }

        $staff = $query->orderBy('name')->paginate(20)->withQueryString();
        $roleOptions = Role::whereIn('slug', self::STAFF_ROLE_SLUGS)->orderBy('sort_order')->get();

        return view('admin.staff.index', [
            'staff' => $staff,
            'roleOptions' => $roleOptions,
            'protectedAdminId' => self::PROTECTED_ADMIN_ID,
        ]);
    }

    /**
     * Форма создания администратора/менеджера.
     */
    public function create(): View
    {
        $roleOptions = Role::whereIn('slug', self::STAFF_ROLE_SLUGS)->orderBy('sort_order')->get();

        return view('admin.staff.create', compact('roleOptions'));
    }

    /**
     * Сохранение нового администратора/менеджера.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ], [], [
            'name' => 'Имя',
            'email' => 'Email',
            'password' => 'Пароль',
            'role_id' => 'Роль',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if (! in_array($role->slug, self::STAFF_ROLE_SLUGS, true)) {
            return back()->withInput()->withErrors(['role_id' => 'Недопустимая роль для сотрудника платформы.']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->roles()->attach($role->id);

        return redirect()->route('admin.staff.index')->with('success', 'Сотрудник успешно создан.');
    }

    /**
     * Форма редактирования.
     */
    public function edit(User $staff): View
    {
        $this->ensureStaffUser($staff);
        $this->rejectProtectedAdmin($staff);

        $roleOptions = Role::whereIn('slug', self::STAFF_ROLE_SLUGS)->orderBy('sort_order')->get();
        $staff->load('roles');

        return view('admin.staff.edit', compact('staff', 'roleOptions'));
    }

    /**
     * Обновление администратора/менеджера.
     */
    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaffUser($staff);
        $this->rejectProtectedAdmin($staff);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $staff->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ], [], [
            'name' => 'Имя',
            'email' => 'Email',
            'password' => 'Пароль',
            'role_id' => 'Роль',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if (! in_array($role->slug, self::STAFF_ROLE_SLUGS, true)) {
            return back()->withInput()->withErrors(['role_id' => 'Недопустимая роль для сотрудника платформы.']);
        }

        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        if (! empty($validated['password'])) {
            $staff->password = Hash::make($validated['password']);
        }
        $staff->save();

        // Синхронизируем только роли «сотрудника платформы»: снять админ/менеджер, надеть выбранную
        $staff->roles()->whereIn('slug', self::STAFF_ROLE_SLUGS)->detach();
        $staff->roles()->attach($role->id);

        return redirect()->route('admin.staff.index')->with('success', 'Данные сотрудника обновлены.');
    }

    /**
     * Удаление администратора/менеджера.
     */
    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaffUser($staff);
        $this->rejectProtectedAdmin($staff);

        if ($staff->id === $request->user()->id) {
            return redirect()->route('admin.staff.index')->with('error', 'Нельзя удалить свою учётную запись.');
        }

        $staff->roles()->whereIn('slug', self::STAFF_ROLE_SLUGS)->detach();

        // Если у пользователя больше нет ролей — удаляем аккаунт
        if ($staff->roles()->count() === 0) {
            $staff->delete();
        }

        return redirect()->route('admin.staff.index')->with('success', 'Сотрудник удалён из панели управления.');
    }

    /**
     * Временная блокировка доступа (учётная запись остаётся, вход невозможен).
     */
    public function suspend(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaffUser($staff);
        $this->rejectProtectedAdmin($staff);

        if ($staff->id === $request->user()->id) {
            return redirect()->route('admin.staff.index')->with('error', 'Нельзя заблокировать свою учётную запись.');
        }

        if (! $staff->is_active) {
            return redirect()->route('admin.staff.index')->with('error', 'Учётная запись уже заблокирована.');
        }

        $staff->is_active = false;
        $staff->save();

        return redirect()->route('admin.staff.index')->with('success', 'Доступ сотрудника временно заблокирован.');
    }

    /**
     * Снятие блокировки доступа.
     */
    public function activate(User $staff): RedirectResponse
    {
        $this->ensureStaffUser($staff);

        if ($staff->is_active) {
            return redirect()->route('admin.staff.index')->with('error', 'Учётная запись уже активна.');
        }

        $staff->is_active = true;
        $staff->save();

        return redirect()->route('admin.staff.index')->with('success', 'Доступ сотрудника восстановлен.');
    }

    private function ensureStaffUser(User $user): void
    {
        if (! $user->hasAnyRole(self::STAFF_ROLE_SLUGS)) {
            abort(404, 'Пользователь не является сотрудником платформы.');
        }
    }

    private function rejectProtectedAdmin(User $user): void
    {
        if ($user->id === self::PROTECTED_ADMIN_ID) {
            abort(403, 'Редактирование и удаление главного администратора запрещены.');
        }
    }
}

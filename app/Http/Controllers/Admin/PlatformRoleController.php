<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformRoleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Role::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $roles = $query->paginate(25)->withQueryString();

        return view('admin.platform-roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.platform-roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        Role::query()->create($validated);

        return redirect()->route('admin.platform-roles.index')->with('success', 'Роль добавлена.');
    }

    public function edit(Role $platformRole): View
    {
        return view('admin.platform-roles.edit', ['role' => $platformRole]);
    }

    public function update(Request $request, Role $platformRole): RedirectResponse
    {
        $validated = $this->validated($request, $platformRole, slugLocked: in_array($platformRole->slug, Role::protectedSlugs(), true));

        if (in_array($platformRole->slug, Role::protectedSlugs(), true)) {
            unset($validated['slug']);
        }

        $platformRole->update($validated);

        return redirect()->route('admin.platform-roles.index')->with('success', 'Роль обновлена.');
    }

    public function destroy(Role $platformRole): RedirectResponse
    {
        if (in_array($platformRole->slug, Role::protectedSlugs(), true)) {
            return redirect()->route('admin.platform-roles.index')
                ->with('error', 'Системную роль нельзя удалить. Деактивируйте запись.');
        }

        if ($platformRole->isInUse()) {
            return redirect()->route('admin.platform-roles.index')
                ->with('error', 'Нельзя удалить роль: она назначена пользователям. Деактивируйте запись.');
        }

        $platformRole->delete();

        return redirect()->route('admin.platform-roles.index')->with('success', 'Роль удалена.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null, bool $slugLocked = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if (! $slugLocked) {
            $rules['slug'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ];
        }

        $validated = $request->validate($rules, [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'description' => 'Описание',
            'sort_order' => 'Порядок',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

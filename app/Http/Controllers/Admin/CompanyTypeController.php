<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyType;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyTypeController extends Controller
{
    public function index(Request $request): View
    {
        $query = CompanyType::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $companyTypes = $query->paginate(25)->withQueryString();

        return view('admin.company-types.index', compact('companyTypes'));
    }

    public function create(): View
    {
        return view('admin.company-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $companyType = CompanyType::query()->create($validated);
        $companyType->syncLinkedRole();

        if (! Role::query()->where('slug', $companyType->slug)->exists()) {
            Role::query()->create([
                'slug' => $companyType->slug,
                'name' => $companyType->name,
                'description' => $companyType->description,
                'sort_order' => $companyType->sort_order,
                'is_active' => $companyType->is_active,
            ]);
        }

        return redirect()->route('admin.company-types.index')->with('success', 'Тип компании добавлен.');
    }

    public function edit(CompanyType $companyType): View
    {
        return view('admin.company-types.edit', compact('companyType'));
    }

    public function update(Request $request, CompanyType $companyType): RedirectResponse
    {
        $validated = $this->validated($request, $companyType);

        $companyType->update($validated);
        $companyType->syncLinkedRole();

        return redirect()->route('admin.company-types.index')->with('success', 'Тип компании обновлён.');
    }

    public function destroy(CompanyType $companyType): RedirectResponse
    {
        if ($companyType->isInUse()) {
            return redirect()->route('admin.company-types.index')
                ->with('error', 'Нельзя удалить тип компании: он используется в системе. Деактивируйте запись.');
        }

        $companyType->delete();

        return redirect()->route('admin.company-types.index')->with('success', 'Тип компании удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CompanyType $companyType = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('company_types', 'slug')->ignore($companyType?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'description' => 'Описание',
            'sort_order' => 'Порядок',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

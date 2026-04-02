<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitTypeController extends Controller
{
    public function index(Request $request): View
    {
        $query = UnitType::query()->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('short_name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $unitTypes = $query->paginate(25)->withQueryString();

        return view('admin.unit-types.index', compact('unitTypes'));
    }

    public function create(): View
    {
        return view('admin.unit-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:unit_types,code'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'short_name' => 'Кратко',
            'code' => 'Код',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        UnitType::query()->create($validated);

        return redirect()->route('admin.unit-types.index')->with('success', 'Единица измерения добавлена.');
    }

    public function edit(UnitType $unitType): View
    {
        return view('admin.unit-types.edit', ['unitType' => $unitType]);
    }

    public function update(Request $request, UnitType $unitType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('unit_types', 'code')->ignore($unitType->id)],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'short_name' => 'Кратко',
            'code' => 'Код',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $unitType->update($validated);

        return redirect()->route('admin.unit-types.index')->with('success', 'Единица измерения обновлена.');
    }

    public function destroy(UnitType $unitType): RedirectResponse
    {
        if (Product::withTrashed()->where('unit_type_id', $unitType->id)->exists()) {
            return redirect()->route('admin.unit-types.index')
                ->with('error', 'Нельзя удалить единицу измерения: она указана в номенклатуре. Деактивируйте запись.');
        }

        $unitType->delete();

        return redirect()->route('admin.unit-types.index')->with('success', 'Единица измерения удалена.');
    }
}

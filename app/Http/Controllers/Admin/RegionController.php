<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Region::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $sort = $request->get('sort', 'name');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'code', 'federal_district'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
            $dir = 'asc';
        }

        $query->orderBy($sort, $dir);
        if ($sort !== 'name') {
            $query->orderBy('name');
        }

        $regions = $query->paginate(25)->withQueryString();
        $districts = Region::federalDistricts();

        return view('admin.regions.index', compact('regions', 'districts', 'sort', 'dir'));
    }

    public function create(): View
    {
        return view('admin.regions.create', [
            'districts' => Region::federalDistricts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $districts = Region::federalDistricts();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:regions,name'],
            'code' => ['nullable', 'string', 'max:10'],
            'federal_district' => ['nullable', 'string', 'max:255', Rule::in($districts)],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'code' => 'Код',
            'federal_district' => 'Федеральный округ',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        Region::query()->create($validated);

        return redirect()->route('admin.regions.index')->with('success', 'Регион добавлен.');
    }

    public function edit(Region $region): View
    {
        return view('admin.regions.edit', [
            'region' => $region,
            'districts' => Region::federalDistricts(),
        ]);
    }

    public function update(Request $request, Region $region): RedirectResponse
    {
        $districts = Region::federalDistricts();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('regions', 'name')->ignore($region->id)],
            'code' => ['nullable', 'string', 'max:10'],
            'federal_district' => ['nullable', 'string', 'max:255', Rule::in($districts)],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'code' => 'Код',
            'federal_district' => 'Федеральный округ',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $region->update($validated);

        return redirect()->route('admin.regions.index')->with('success', 'Регион обновлён.');
    }

    public function destroy(Region $region): RedirectResponse
    {
        if ($this->regionInUse($region)) {
            return redirect()->route('admin.regions.index')
                ->with('error', 'Нельзя удалить регион: он используется в профилях производителей, складах, товарах или ценах по регионам. Деактивируйте запись вместо удаления.');
        }

        $region->delete();

        return redirect()->route('admin.regions.index')->with('success', 'Регион удалён.');
    }

    private function regionInUse(Region $region): bool
    {
        return $region->manufacturerProfiles()->exists()
            || $region->warehouses()->exists()
            || $region->products()->exists()
            || $region->regionalPrices()->exists();
    }
}

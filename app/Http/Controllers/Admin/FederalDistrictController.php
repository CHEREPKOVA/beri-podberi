<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FederalDistrict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FederalDistrictController extends Controller
{
    public function index(Request $request): View
    {
        $query = FederalDistrict::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%{$s}%");
        }

        $districts = $query->paginate(25)->withQueryString();

        return view('admin.federal-districts.index', compact('districts'));
    }

    public function create(): View
    {
        return view('admin.federal-districts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        FederalDistrict::query()->create($this->validated($request));

        return redirect()->route('admin.federal-districts.index')->with('success', 'Федеральный округ добавлен.');
    }

    public function edit(FederalDistrict $federalDistrict): View
    {
        return view('admin.federal-districts.edit', ['district' => $federalDistrict]);
    }

    public function update(Request $request, FederalDistrict $federalDistrict): RedirectResponse
    {
        $oldName = $federalDistrict->name;
        $validated = $this->validated($request, $federalDistrict);
        $federalDistrict->update($validated);

        if ($oldName !== $federalDistrict->name) {
            \App\Models\Region::query()
                ->where('federal_district', $oldName)
                ->update(['federal_district' => $federalDistrict->name]);
        }

        return redirect()->route('admin.federal-districts.index')->with('success', 'Федеральный округ обновлён.');
    }

    public function destroy(FederalDistrict $federalDistrict): RedirectResponse
    {
        if ($federalDistrict->isInUse()) {
            return redirect()->route('admin.federal-districts.index')
                ->with('error', 'Нельзя удалить округ: он указан у регионов. Деактивируйте запись.');
        }

        $federalDistrict->delete();

        return redirect()->route('admin.federal-districts.index')->with('success', 'Федеральный округ удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?FederalDistrict $district = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('federal_districts', 'name')->ignore($district?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'sort_order' => 'Порядок',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

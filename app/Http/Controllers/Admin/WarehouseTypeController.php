<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarehouseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseTypeController extends Controller
{
    public function index(Request $request): View
    {
        $query = WarehouseType::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        if ($request->filled('applies_to')) {
            $query->where(function ($q) use ($request) {
                $q->where('applies_to', $request->string('applies_to')->toString())
                    ->orWhere('applies_to', WarehouseType::APPLIES_BOTH);
            });
        }

        $warehouseTypes = $query->paginate(25)->withQueryString();

        return view('admin.warehouse-types.index', [
            'warehouseTypes' => $warehouseTypes,
            'appliesToLabels' => WarehouseType::appliesToLabels(),
        ]);
    }

    public function create(): View
    {
        return view('admin.warehouse-types.create', [
            'appliesToLabels' => WarehouseType::appliesToLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WarehouseType::query()->create($this->validated($request));

        return redirect()->route('admin.warehouse-types.index')->with('success', 'Тип склада добавлен.');
    }

    public function edit(WarehouseType $warehouseType): View
    {
        return view('admin.warehouse-types.edit', [
            'warehouseType' => $warehouseType,
            'appliesToLabels' => WarehouseType::appliesToLabels(),
        ]);
    }

    public function update(Request $request, WarehouseType $warehouseType): RedirectResponse
    {
        $warehouseType->update($this->validated($request, $warehouseType));

        return redirect()->route('admin.warehouse-types.index')->with('success', 'Тип склада обновлён.');
    }

    public function destroy(WarehouseType $warehouseType): RedirectResponse
    {
        if ($warehouseType->isInUse()) {
            return redirect()->route('admin.warehouse-types.index')
                ->with('error', 'Нельзя удалить тип склада: он используется. Деактивируйте запись.');
        }

        $warehouseType->delete();

        return redirect()->route('admin.warehouse-types.index')->with('success', 'Тип склада удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WarehouseType $warehouseType = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('warehouse_types', 'slug')->ignore($warehouseType?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'applies_to' => ['required', 'string', Rule::in(array_keys(WarehouseType::appliesToLabels()))],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'description' => 'Описание',
            'applies_to' => 'Применимость',
            'sort_order' => 'Порядок',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

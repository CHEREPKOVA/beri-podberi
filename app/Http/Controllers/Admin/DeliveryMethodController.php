<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeliveryMethodController extends Controller
{
    public function index(): View
    {
        $methods = DeliveryMethod::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.delivery-methods.index', compact('methods'));
    }

    public function create(): View
    {
        return view('admin.delivery-methods.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:delivery_methods,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requires_tracking' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
        ]);

        $validated['requires_tracking'] = $request->boolean('requires_tracking');
        $validated['is_active'] = $request->boolean('is_active');

        DeliveryMethod::query()->create($validated);

        return redirect()->route('admin.delivery-methods.index')->with('success', 'Способ доставки добавлен.');
    }

    public function edit(DeliveryMethod $deliveryMethod): View
    {
        return view('admin.delivery-methods.edit', ['method' => $deliveryMethod]);
    }

    public function update(Request $request, DeliveryMethod $deliveryMethod): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('delivery_methods', 'slug')->ignore($deliveryMethod->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'requires_tracking' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
        ]);

        $validated['requires_tracking'] = $request->boolean('requires_tracking');
        $validated['is_active'] = $request->boolean('is_active');

        $deliveryMethod->update($validated);

        return redirect()->route('admin.delivery-methods.index')->with('success', 'Способ доставки обновлён.');
    }

    public function destroy(DeliveryMethod $deliveryMethod): RedirectResponse
    {
        if ($deliveryMethod->manufacturerProfiles()->exists()) {
            return redirect()->route('admin.delivery-methods.index')
                ->with('error', 'Нельзя удалить способ доставки: он выбран у производителей. Деактивируйте запись.');
        }

        $deliveryMethod->delete();

        return redirect()->route('admin.delivery-methods.index')->with('success', 'Способ доставки удалён.');
    }
}

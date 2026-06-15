<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function index(Request $request): View
    {
        $query = OrderStatus::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $statuses = $query->paginate(25)->withQueryString();

        return view('admin.order-statuses.index', compact('statuses'));
    }

    public function create(): View
    {
        return view('admin.order-statuses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        OrderStatus::query()->create($this->validated($request));

        return redirect()->route('admin.order-statuses.index')->with('success', 'Статус заказа добавлен.');
    }

    public function edit(OrderStatus $orderStatus): View
    {
        return view('admin.order-statuses.edit', ['status' => $orderStatus]);
    }

    public function update(Request $request, OrderStatus $orderStatus): RedirectResponse
    {
        $orderStatus->update($this->validated($request, $orderStatus));

        return redirect()->route('admin.order-statuses.index')->with('success', 'Статус заказа обновлён.');
    }

    public function destroy(OrderStatus $orderStatus): RedirectResponse
    {
        if ($orderStatus->isInUse()) {
            return redirect()->route('admin.order-statuses.index')
                ->with('error', 'Нельзя удалить статус: он используется в заказах. Деактивируйте запись.');
        }

        $orderStatus->delete();

        return redirect()->route('admin.order-statuses.index')->with('success', 'Статус заказа удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?OrderStatus $status = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('order_statuses', 'slug')->ignore($status?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_terminal' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'description' => 'Описание',
            'sort_order' => 'Порядок',
            'is_terminal' => 'Финальный статус',
        ]);

        $validated['is_terminal'] = $request->boolean('is_terminal');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

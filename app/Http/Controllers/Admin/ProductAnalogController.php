<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductAnalogController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with(['manufacturerProfile', 'category'])->withCount('analogs');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $products = $query->latest('updated_at')->paginate(25)->withQueryString();

        return view('admin.catalog.analogs.index', compact('products'));
    }

    public function edit(Product $product): View
    {
        $product->load(['manufacturerProfile', 'category', 'analogs', 'analogOf']);
        $allProducts = Product::query()
            ->where('id', '!=', $product->id)
            ->orderBy('name')
            ->limit(500)
            ->get();

        $selectedIds = $product->analogs->pluck('id')
            ->merge($product->analogOf->pluck('id'))
            ->unique()
            ->values()
            ->all();

        return view('admin.catalog.analogs.edit', compact('product', 'allProducts', 'selectedIds'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'analog_ids' => ['nullable', 'array'],
            'analog_ids.*' => ['exists:products,id'],
        ]);

        $analogIds = array_values(array_filter(
            $validated['analog_ids'] ?? [],
            fn ($id) => (int) $id !== (int) $product->id
        ));

        // Перестраиваем двусторонние связи аналогов, чтобы не оставлять "односторонних" пар.
        $oldIds = $product->analogs()->pluck('products.id')->merge($product->analogOf()->pluck('products.id'))->unique()->all();
        $detachIds = array_values(array_diff($oldIds, $analogIds));

        foreach ($detachIds as $detachId) {
            $product->analogs()->detach($detachId);
            $product->analogOf()->detach($detachId);
            Product::query()->find($detachId)?->analogs()->detach($product->id);
            Product::query()->find($detachId)?->analogOf()->detach($product->id);
        }

        foreach ($analogIds as $analogId) {
            $product->analogs()->syncWithoutDetaching([$analogId]);
            Product::query()->find($analogId)?->analogs()->syncWithoutDetaching([$product->id]);
        }

        return redirect()->route('admin.catalog.analogs.edit', $product)->with('success', 'Связи аналогов обновлены.');
    }
}

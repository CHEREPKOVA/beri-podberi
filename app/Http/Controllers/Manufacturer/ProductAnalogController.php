<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductAnalogController extends Controller
{
    public function index(Request $request): View
    {
        $profileId = $request->user()->manufacturerProfile?->id;

        $query = Product::query()
            ->forManufacturer($profileId)
            ->with(['category'])
            ->withCount(['analogs', 'analogOf']);

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $products = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('manufacturer.catalog.analogs.index', compact('products'));
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        $product->load(['category', 'analogs', 'analogOf']);

        $allProducts = Product::query()
            ->where('id', '!=', $product->id)
            ->published()
            ->orderBy('name')
            ->limit(500)
            ->get();

        $selectedIds = $product->allAnalogIds();

        return view('manufacturer.catalog.analogs.edit', compact('product', 'allProducts', 'selectedIds'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'analog_ids' => ['nullable', 'array'],
            'analog_ids.*' => ['exists:products,id'],
        ]);

        $analogIds = array_values(array_filter(
            $validated['analog_ids'] ?? [],
            fn ($id) => (int) $id !== (int) $product->id
        ));

        $oldIds = $product->allAnalogIds();
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

        return redirect()->route('manufacturer.catalog.analogs.edit', $product)
            ->with('success', 'Связи аналогов обновлены.');
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        if ((int) $product->manufacturer_profile_id !== (int) $request->user()->manufacturerProfile?->id) {
            abort(403);
        }
    }
}

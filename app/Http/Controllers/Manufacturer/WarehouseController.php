<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->getOrCreateManufacturerProfile();
        $profile->load(['warehouses.region', 'contacts']);

        $regions = Region::active()->orderBy('name')->get();
        $warehouseIds = $profile->warehouses->pluck('id')->all();
        $selectedWarehouseId = (int) $request->integer('stock_warehouse_id');
        if (! in_array($selectedWarehouseId, $warehouseIds, true)) {
            $selectedWarehouseId = (int) ($profile->warehouses->first()?->id ?? 0);
        }

        $stockQuery = ProductStock::query()
            ->with(['product', 'warehouse', 'updatedByUser'])
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereHas('product', fn ($q) => $q->where('manufacturer_profile_id', $profile->id));

        if ($selectedWarehouseId > 0) {
            $stockQuery->where('warehouse_id', $selectedWarehouseId);
        }

        if ($request->filled('stock_search')) {
            $search = trim((string) $request->input('stock_search'));
            $stockQuery->whereHas('product', function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $stockItems = $stockQuery
            ->orderByDesc('stock_updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('manufacturer.warehouses.index', compact('profile', 'regions', 'stockItems', 'selectedWarehouseId'));
    }

    public function updateStock(Request $request): RedirectResponse
    {
        $profile = $request->user()->getOrCreateManufacturerProfile();

        $validated = $request->validate([
            'product_stock_id' => 'required|integer|exists:product_stocks,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $stock = ProductStock::query()
            ->with(['product', 'warehouse'])
            ->findOrFail($validated['product_stock_id']);

        if (
            $stock->warehouse?->manufacturer_profile_id !== $profile->id
            || $stock->product?->manufacturer_profile_id !== $profile->id
        ) {
            abort(403);
        }

        $stock->update([
            'quantity' => (int) $validated['quantity'],
            'stock_updated_at' => now(),
            'stock_updated_by_user_id' => $request->user()->id,
        ]);

        if ((int) $validated['quantity'] === 0) {
            $stock->product?->update([
                'status' => Product::STATUS_HIDDEN,
                'show_in_catalog' => false,
            ]);
        }

        return back()->with('success', 'Остаток обновлён вручную');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    public function export(): StreamedResponse
    {
        $rows = Product::query()
            ->with(['analogs:id,sku'])
            ->select(['id', 'sku'])
            ->get()
            ->flatMap(function (Product $product) {
                return $product->analogs->map(function (Product $analog) use ($product) {
                    return [$product->sku, $analog->sku];
                });
            })
            ->unique(fn ($pair) => implode('|', collect($pair)->sort()->values()->all()))
            ->values();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="product_analogs_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, ['product_sku', 'analog_sku'], ';');
            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }
            fclose($output);
        }, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getPathname();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->with('error', 'Не удалось прочитать файл.');
        }

        $header = fgetcsv($handle, 0, ';');
        if (! is_array($header)) {
            fclose($handle);
            return back()->with('error', 'Файл пустой или имеет неверный формат.');
        }

        $normalizedHeader = array_map(static fn ($col) => strtolower(trim((string) $col)), $header);
        $productIdx = array_search('product_sku', $normalizedHeader, true);
        $analogIdx = array_search('analog_sku', $normalizedHeader, true);
        if ($productIdx === false || $analogIdx === false) {
            fclose($handle);
            return back()->with('error', 'Ожидаются колонки product_sku и analog_sku.');
        }

        $processed = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $productSku = trim((string) ($row[$productIdx] ?? ''));
            $analogSku = trim((string) ($row[$analogIdx] ?? ''));
            if ($productSku === '' || $analogSku === '' || $productSku === $analogSku) {
                continue;
            }

            $product = Product::query()->where('sku', $productSku)->first();
            $analog = Product::query()->where('sku', $analogSku)->first();
            if (! $product || ! $analog) {
                continue;
            }

            $product->analogs()->syncWithoutDetaching([$analog->id]);
            $analog->analogs()->syncWithoutDetaching([$product->id]);
            $processed++;
        }
        fclose($handle);

        return back()->with('success', "Импорт завершен. Обработано связей: {$processed}.");
    }
}

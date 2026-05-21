<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\DistributorProduct;
use App\Models\DistributorProductDocument;
use App\Models\DistributorProductPriceHistory;
use App\Models\DistributorProductStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DistributorProductLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->getOrCreateDistributorProfile();

        $query = DistributorProduct::forDistributor($profile->id)
            ->with(['category', 'manufacturerProfile', 'stocks.warehouse'])
            ->withCount('stocks');

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('category')) {
            $query->where('product_category_id', $request->integer('category'));
        }

        if ($request->filled('brand')) {
            $query->where('brand', 'like', '%'.$request->string('brand').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('has_stock')) {
            $query->withStockFilter($request->string('has_stock')->toString());
        }

        if ($request->filled('price_min')) {
            $query->where('retail_price', '>=', (float) $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $query->where('retail_price', '<=', (float) $request->input('price_max'));
        }

        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->date('updated_from'));
        }

        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->date('updated_to'));
        }

        $sortField = $request->get('sort', 'updated_at');
        $allowedSorts = ['name', 'internal_sku', 'retail_price', 'purchase_price', 'updated_at'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'updated_at';
        }
        $sortDir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $products = $query->paginate(25)->withQueryString();

        $categories = ProductCategory::query()
            ->whereIn('id', $profile->productCategories()->pluck('product_categories.id'))
            ->orWhereIn('id', DistributorProduct::forDistributor($profile->id)->whereNotNull('product_category_id')->distinct()->pluck('product_category_id'))
            ->orderBy('name')
            ->get()
            ->unique('id');

        $brands = DistributorProduct::forDistributor($profile->id)
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $managedBy1c = $profile->integration_import_1c_stocks;

        return view('distributor.products.index', compact('products', 'categories', 'brands', 'managedBy1c'));
    }

    public function show(Request $request, DistributorProduct $product): View
    {
        $this->authorizeProduct($request, $product);

        $product->load([
            'category',
            'manufacturerProfile',
            'unitType',
            'sourceProduct.images',
            'sourceProduct.attributeValues.attribute',
            'sourceProduct.documents',
            'stocks.warehouse.region',
            'priceHistories.changedByUser',
            'changeLogs.performedByUser',
            'documents',
        ]);

        $profile = $request->user()->getOrCreateDistributorProfile();
        $warehouses = $profile->warehouses()->with('region')->orderBy('name')->get();
        $tab = $request->get('tab', 'info');
        $managedBy1c = $profile->integration_import_1c_stocks || $product->managed_by_1c;
        $stockEditingDisabled = $managedBy1c && $product->isSyncedFrom1c();

        return view('distributor.products.show', compact(
            'product',
            'warehouses',
            'tab',
            'managedBy1c',
            'stockEditingDisabled',
        ));
    }

    public function update(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($product->managed_by_1c && $product->isSyncedFrom1c()) {
            $data = $request->validate([
                'internal_sku' => ['required', 'string', 'max:100'],
                'short_description' => ['nullable', 'string', 'max:2000'],
                'description' => ['nullable', 'string'],
            ]);
        } else {
            $data = $request->validate([
                'internal_sku' => ['required', 'string', 'max:100'],
                'name' => ['required', 'string', 'max:500'],
                'manufacturer_sku' => ['nullable', 'string', 'max:100'],
                'brand' => ['nullable', 'string', 'max:255'],
                'barcode' => ['nullable', 'string', 'max:64'],
                'short_description' => ['nullable', 'string', 'max:2000'],
                'description' => ['nullable', 'string'],
                'country_of_origin' => ['nullable', 'string', 'max:100'],
                'pack_quantity' => ['nullable', 'integer', 'min:1'],
                'min_order_quantity' => ['nullable', 'integer', 'min:1'],
                'product_category_id' => ['nullable', 'exists:product_categories,id'],
            ]);
        }

        $product->update($data);

        DistributorProductLogger::log(
            $product,
            'info_updated',
            'Обновлена основная информация',
            null,
            $request->user(),
        );

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'info'])
            ->with('success', 'Информация сохранена.');
    }

    public function updatePrice(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'price_type' => ['required', 'in:purchase,retail'],
            'new_price' => ['required', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'effective_at' => ['nullable', 'date'],
        ]);

        $field = $validated['price_type'] === 'purchase' ? 'purchase_price' : 'retail_price';
        $oldPrice = $product->{$field};

        $product->update([
            $field => $validated['new_price'],
            'price_updated_at' => now(),
        ]);

        DistributorProductPriceHistory::create([
            'distributor_product_id' => $product->id,
            'price_type' => $validated['price_type'],
            'old_price' => $oldPrice,
            'new_price' => $validated['new_price'],
            'comment' => $validated['comment'] ?? null,
            'effective_at' => $validated['effective_at'] ?? now(),
            'changed_by_user_id' => $request->user()->id,
        ]);

        DistributorProductLogger::log(
            $product,
            'price_changed',
            sprintf(
                'Изменена %s цена: %s → %s ₽',
                $validated['price_type'] === 'purchase' ? 'закупочная' : 'отпускная',
                $oldPrice !== null ? number_format((float) $oldPrice, 2, ',', ' ') : '—',
                number_format((float) $validated['new_price'], 2, ',', ' '),
            ),
            ['price_type' => $validated['price_type']],
            $request->user(),
        );

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'prices'])
            ->with('success', 'Цена обновлена.');
    }

    public function updateStocks(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $profile = $request->user()->getOrCreateDistributorProfile();

        if ($profile->integration_import_1c_stocks && $product->managed_by_1c) {
            return back()->with('error', 'Остатки управляются из 1С. Ручное изменение недоступно.');
        }

        $validated = $request->validate([
            'stocks' => ['required', 'array'],
            'stocks.*.warehouse_id' => ['required', 'exists:distributor_warehouses,id'],
            'stocks.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $product, $request, $profile) {
            foreach ($validated['stocks'] as $row) {
                $warehouseId = (int) $row['warehouse_id'];
                if (! $profile->warehouses()->whereKey($warehouseId)->exists()) {
                    continue;
                }

                $stock = DistributorProductStock::query()->firstOrNew([
                    'distributor_product_id' => $product->id,
                    'distributor_warehouse_id' => $warehouseId,
                ]);

                $oldQty = $stock->exists ? $stock->quantity : 0;
                $stock->fill([
                    'quantity' => (int) $row['quantity'],
                    'stock_updated_at' => now(),
                    'stock_updated_by_user_id' => $request->user()->id,
                ]);
                $stock->save();

                if ($oldQty !== (int) $row['quantity']) {
                    DistributorProductLogger::log(
                        $product,
                        'stock_changed',
                        sprintf('Остаток на складе #%d: %d → %d', $warehouseId, $oldQty, (int) $row['quantity']),
                        ['warehouse_id' => $warehouseId],
                        $request->user(),
                    );
                }
            }
        });

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'stocks'])
            ->with('success', 'Остатки обновлены.');
    }

    public function publish(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['status' => DistributorProduct::STATUS_ACTIVE]);
        DistributorProductLogger::log($product, 'published', 'Товар опубликован', null, $request->user());

        return back()->with('success', 'Товар опубликован в каталоге для клиентов.');
    }

    public function hide(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['status' => DistributorProduct::STATUS_HIDDEN]);
        DistributorProductLogger::log($product, 'hidden', 'Товар скрыт', null, $request->user());

        return back()->with('success', 'Товар скрыт от клиентов.');
    }

    public function archive(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['status' => DistributorProduct::STATUS_ARCHIVE]);
        DistributorProductLogger::log($product, 'archived', 'Товар переведён в архив', null, $request->user());

        return back()->with('success', 'Товар переведён в архив.');
    }

    public function importForm(Request $request): View
    {
        $profile = $request->user()->getOrCreateDistributorProfile();

        return view('distributor.products.import', compact('profile'));
    }

    public function import(Request $request): RedirectResponse
    {
        $profile = $request->user()->getOrCreateDistributorProfile();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'import_type' => ['required', 'in:prices,stocks,full'],
        ]);

        $path = $request->file('file')->store('imports/distributor/'.$profile->id, 'local');
        $handle = fopen(Storage::disk('local')->path($path), 'r');
        if ($handle === false) {
            return back()->with('error', 'Не удалось прочитать файл.');
        }

        $header = fgetcsv($handle, 0, ';') ?: fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return back()->with('error', 'Файл пуст или неверный формат.');
        }

        $header = array_map(static fn ($h) => mb_strtolower(trim((string) $h)), $header);
        $skuIdx = array_search('internal_sku', $header, true);
        if ($skuIdx === false) {
            $skuIdx = array_search('артикул', $header, true);
        }
        if ($skuIdx === false) {
            fclose($handle);

            return back()->with('error', 'В файле должна быть колонка internal_sku или «артикул».');
        }

        $retailIdx = array_search('retail_price', $header, true);
        if ($retailIdx === false) {
            $retailIdx = array_search('цена', $header, true);
        }
        $stockIdx = array_search('quantity', $header, true);
        if ($stockIdx === false) {
            $stockIdx = array_search('остаток', $header, true);
        }

        $updated = 0;
        $errors = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 2) {
                continue;
            }
            $sku = trim((string) ($row[$skuIdx] ?? ''));
            if ($sku === '') {
                continue;
            }

            $product = DistributorProduct::forDistributor($profile->id)
                ->where('internal_sku', $sku)
                ->first();

            if (! $product) {
                $errors[] = "Товар с артикулом «{$sku}» не найден.";

                continue;
            }

            if ($request->import_type !== 'stocks' && $retailIdx !== false && isset($row[$retailIdx]) && $row[$retailIdx] !== '') {
                $newPrice = (float) str_replace(',', '.', $row[$retailIdx]);
                $old = $product->retail_price;
                $product->update([
                    'retail_price' => $newPrice,
                    'price_updated_at' => now(),
                    'sync_source' => DistributorProduct::SYNC_CSV,
                    'synced_at' => now(),
                ]);
                DistributorProductPriceHistory::create([
                    'distributor_product_id' => $product->id,
                    'price_type' => DistributorProductPriceHistory::TYPE_RETAIL,
                    'old_price' => $old,
                    'new_price' => $newPrice,
                    'comment' => 'Импорт CSV',
                    'changed_by_user_id' => $request->user()->id,
                ]);
            }

            if ($request->import_type !== 'prices' && $stockIdx !== false && isset($row[$stockIdx]) && $row[$stockIdx] !== '') {
                $warehouse = $profile->warehouses()->active()->first();
                if ($warehouse) {
                    DistributorProductStock::query()->updateOrCreate(
                        [
                            'distributor_product_id' => $product->id,
                            'distributor_warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => (int) $row[$stockIdx],
                            'stock_updated_at' => now(),
                            'stock_updated_by_user_id' => $request->user()->id,
                        ],
                    );
                }
            }

            $product->update(['sync_source' => DistributorProduct::SYNC_CSV, 'synced_at' => now()]);
            DistributorProductLogger::log($product, 'csv_import', 'Обновление из CSV', null, $request->user());
            $updated++;
        }

        fclose($handle);
        Storage::disk('local')->delete($path);

        $message = "Импорт завершён. Обновлено позиций: {$updated}.";
        if ($errors !== []) {
            return back()
                ->with('warning', $message)
                ->with('import_errors', array_slice($errors, 0, 20));
        }

        return back()->with('success', $message);
    }

    public function storeDocument(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($product->status === DistributorProduct::STATUS_ARCHIVE) {
            return back()->with('error', 'Нельзя добавлять документы к архивному товару.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(DistributorProductDocument::typeLabels()))],
            'file' => ['required', 'file', 'max:10240'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $path = $file->store('distributor-products/'.$product->id, 'public');

        $product->documents()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'is_internal' => $request->boolean('is_internal'),
        ]);

        DistributorProductLogger::log($product, 'document_added', 'Добавлен документ: '.$validated['name'], null, $request->user());

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'documents'])
            ->with('success', 'Документ загружен.');
    }

    public function deleteDocument(Request $request, DistributorProduct $product, DistributorProductDocument $document): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($document->distributor_product_id !== $product->id) {
            abort(404);
        }

        if ($product->status === DistributorProduct::STATUS_ARCHIVE) {
            return back()->with('error', 'Нельзя удалять документы архивного товара.');
        }

        Storage::disk('public')->delete($document->path);
        $name = $document->name;
        $document->delete();

        DistributorProductLogger::log($product, 'document_removed', 'Удалён документ: '.$name, null, $request->user());

        return back()->with('success', 'Документ удалён.');
    }

    protected function authorizeProduct(Request $request, DistributorProduct $product): void
    {
        $profile = $request->user()->getOrCreateDistributorProfile();
        if ($product->distributor_profile_id !== $profile->id) {
            abort(403);
        }
    }
}

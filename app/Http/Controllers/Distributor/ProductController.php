<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\DistributorProduct;
use App\Models\DistributorProductDocument;
use App\Models\DistributorProductPriceHistory;
use App\Models\DistributorProductStock;
use App\Models\DistributorProductRegionalPrice;
use App\Models\DistributorProfile;
use App\Models\ProductCategory;
use App\Models\UnitType;
use App\Services\CsvImportReader;
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
        $allowedSorts = [
            'name', 'internal_sku', 'manufacturer_sku', 'brand', 'retail_price',
            'purchase_price', 'updated_at', 'status', 'category', 'stock', 'sync_source',
        ];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'updated_at';
        }
        $sortDir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        match ($sortField) {
            'category' => $query->orderBy(
                ProductCategory::select('name')
                    ->whereColumn('product_categories.id', 'distributor_products.product_category_id')
                    ->limit(1),
                $sortDir
            ),
            'stock' => $query->orderByRaw(
                '(SELECT COALESCE(SUM(quantity - reserved), 0) FROM distributor_product_stocks WHERE distributor_product_id = distributor_products.id) '.$sortDir
            ),
            default => $query->orderBy($sortField, $sortDir),
        };

        $products = $query->paginate(25)->withQueryString();

        $baseCategories = ProductCategory::query()
            ->whereIn('id', $profile->productCategories()->pluck('product_categories.id'))
            ->orWhereIn('id', DistributorProduct::forDistributor($profile->id)->whereNotNull('product_category_id')->distinct()->pluck('product_category_id'))
            ->orderBy('name')
            ->get()
            ->unique('id');

        $categories = ProductCategory::withAncestors($baseCategories);
        $categoryTree = ProductCategory::buildTree($categories);

        $brands = DistributorProduct::forDistributor($profile->id)
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $managedBy1c = $profile->integration_import_1c_stocks;

        return view('distributor.products.index', compact('products', 'categories', 'categoryTree', 'brands', 'managedBy1c'));
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
            'regionalPrices.region',
        ]);

        $profile = $request->user()->getOrCreateDistributorProfile();
        $categories = $profile->productCategories()->orderBy('name')->get();
        $unitTypes = UnitType::query()->active()->orderBy('name')->get();
        $warehouses = $profile->warehouses()->with('region')->orderBy('name')->get();
        $regions = $profile->regions()->orderBy('name')->get();
        $tab = $request->get('tab', 'info');
        $managedBy1c = $profile->integration_import_1c_stocks || $product->managed_by_1c;
        $stockEditingDisabled = $managedBy1c && $product->isSyncedFrom1c();

        return view('distributor.products.show', compact(
            'product',
            'warehouses',
            'categories',
            'unitTypes',
            'regions',
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
                'unit_type_id' => ['nullable', 'exists:unit_types,id'],
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

    public function updateRegionalPrices(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $profile = $request->user()->getOrCreateDistributorProfile();
        $regionIds = $profile->regions()->pluck('regions.id');

        $validated = $request->validate([
            'regional_prices' => ['nullable', 'array'],
            'regional_prices.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $prices = $validated['regional_prices'] ?? [];

        foreach ($regionIds as $regionId) {
            $value = $prices[$regionId] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                $product->regionalPrices()->where('region_id', $regionId)->delete();

                continue;
            }

            DistributorProductRegionalPrice::query()->updateOrCreate(
                [
                    'distributor_product_id' => $product->id,
                    'region_id' => $regionId,
                ],
                ['price' => (float) str_replace(',', '.', (string) $value)],
            );
        }

        DistributorProductLogger::log(
            $product,
            'regional_prices_updated',
            'Обновлены региональные отпускные цены',
            null,
            $request->user(),
        );

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'prices'])
            ->with('success', 'Региональные цены сохранены.');
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
        $oldStatus = $product->status;
        $product->update(['status' => DistributorProduct::STATUS_ACTIVE]);
        DistributorProductLogger::logStatusChange(
            $product,
            $oldStatus,
            DistributorProduct::STATUS_ACTIVE,
            'published',
            $request->user(),
        );

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'publication'])
            ->with('success', 'Товар опубликован в каталоге для клиентов.');
    }

    public function hide(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $oldStatus = $product->status;
        $product->update(['status' => DistributorProduct::STATUS_HIDDEN]);
        DistributorProductLogger::logStatusChange(
            $product,
            $oldStatus,
            DistributorProduct::STATUS_HIDDEN,
            'hidden',
            $request->user(),
        );

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'publication'])
            ->with('success', 'Товар скрыт от клиентов.');
    }

    public function archive(Request $request, DistributorProduct $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $oldStatus = $product->status;
        $product->update(['status' => DistributorProduct::STATUS_ARCHIVE]);
        DistributorProductLogger::logStatusChange(
            $product,
            $oldStatus,
            DistributorProduct::STATUS_ARCHIVE,
            'archived',
            $request->user(),
        );

        return redirect()
            ->route('distributor.products.show', ['product' => $product, 'tab' => 'publication'])
            ->with('success', 'Товар переведён в архив.');
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
            'file' => ['required', 'file', 'mimes:csv,txt,xml,yml', 'max:10240'],
            'import_type' => ['required', 'in:prices,stocks,full'],
        ]);

        $path = $request->file('file')->store('imports/distributor/'.$profile->id, 'local');
        $localPath = Storage::disk('local')->path($path);
        $importType = $request->input('import_type');
        $warehouse = $profile->warehouses()->active()->first();

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $ext = $ext !== '' ? $ext : strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            if (in_array($ext, ['xml', 'yml', 'yaml'], true)) {
                $stats = $this->importXml($localPath, $profile, $importType, $warehouse, $request->user());
            } else {
                $stats = $this->importCsv($localPath, $profile, $importType, $warehouse, $request->user());
            }
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'Ошибка импорта: '.$e->getMessage());
        }

        Storage::disk('local')->delete($path);

        $message = "Импорт завершён. Создано: {$stats['created']}, обновлено: {$stats['updated']}, пропущено: {$stats['skipped']}";
        if ($stats['errors'] !== []) {
            return back()
                ->with('warning', $message)
                ->with('import_errors', array_slice($stats['errors'], 0, 20));
        }

        return back()->with('success', $message);
    }

    /**
     * @param  array<string, int|string|array<mixed>>  $stats
     * @return array<string, int|string|array<mixed>>
     */
    private function importCsv(string $path, DistributorProfile $profile, string $importType, ?\App\Models\DistributorWarehouse $warehouse, $user): array
    {
        $handle = CsvImportReader::open($path);
        ['header' => $header, 'delimiter' => $delimiter] = CsvImportReader::readHeader($handle);

        $headerMap = CsvImportReader::mapHeader($header, [
            'sku' => ['internal_sku', 'артикул', 'sku', 'код', 'vendorcode'],
            'name' => ['name', 'наименование', 'название', 'title', 'товар'],
            'brand' => ['brand', 'бренд'],
            'barcode' => ['barcode', 'штрихкод', 'ean'],
            'manufacturer_sku' => ['manufacturer_sku', 'артикул_производителя', 'артикул производителя'],
            'category_id' => ['product_category_id', 'category_id', 'id категории'],
            'category' => ['category', 'категория'],
            'unit_type_id' => ['unit_type_id', 'единица'],
            'retail_price' => ['retail_price', 'цена', 'price', 'отпускная'],
            'purchase_price' => ['purchase_price', 'закупочная', 'purchase'],
            'stock' => ['quantity', 'остаток', 'stock', 'количество'],
        ]);

        if (! isset($headerMap['sku'])) {
            fclose($handle);

            throw new \Exception('В файле должна быть колонка internal_sku или «артикул».');
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if ($importType !== 'prices' && $warehouse === null) {
            $stats['errors'][] = 'Нет активного склада — остатки из файла не будут загружены.';
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, $delimiter, escape: '\\')) !== false) {
            $rowNum++;

            if ($row === [null] || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $sku = trim((string) ($row[$headerMap['sku']] ?? ''));
            if ($sku === '') {
                $stats['skipped']++;

                continue;
            }

            $name = isset($headerMap['name']) ? trim((string) ($row[$headerMap['name']] ?? '')) : '';
            if ($name === '') {
                $name = $sku;
            }

            $brand = isset($headerMap['brand']) ? trim((string) ($row[$headerMap['brand']] ?? '')) : '';
            $brand = $brand !== '' ? $brand : null;

            $barcode = isset($headerMap['barcode']) ? trim((string) ($row[$headerMap['barcode']] ?? '')) : '';
            $barcode = $barcode !== '' ? $barcode : null;

            $manufacturerSku = isset($headerMap['manufacturer_sku']) ? trim((string) ($row[$headerMap['manufacturer_sku']] ?? '')) : '';
            $manufacturerSku = $manufacturerSku !== '' ? $manufacturerSku : null;

            $categoryId = null;
            if (isset($headerMap['category_id'])) {
                $value = trim((string) ($row[$headerMap['category_id']] ?? ''));
                $categoryId = $value !== '' ? (int) $value : null;
            }

            if ($categoryId === null && isset($headerMap['category'])) {
                $catName = trim((string) ($row[$headerMap['category']] ?? ''));
                if ($catName !== '') {
                    $categoryId = ProductCategory::query()->where('name', $catName)->value('id');
                }
            }

            $unitTypeId = null;
            if (isset($headerMap['unit_type_id'])) {
                $value = trim((string) ($row[$headerMap['unit_type_id']] ?? ''));
                $unitTypeId = $value !== '' ? (int) $value : null;
            }

            $existing = DistributorProduct::forDistributor($profile->id)
                ->where('internal_sku', $sku)
                ->first();

            $priceToUpdate = null;
            if ($importType !== 'stocks' && isset($headerMap['retail_price'])) {
                $raw = trim((string) ($row[$headerMap['retail_price']] ?? ''));
                if ($raw !== '') {
                    $priceToUpdate = (float) str_replace([' ', ','], ['', '.'], $raw);
                }
            }

            $purchaseToUpdate = null;
            if ($importType !== 'stocks' && isset($headerMap['purchase_price'])) {
                $raw = trim((string) ($row[$headerMap['purchase_price']] ?? ''));
                if ($raw !== '') {
                    $purchaseToUpdate = (float) str_replace([' ', ','], ['', '.'], $raw);
                }
            }

            $stockToUpdate = null;
            if ($importType !== 'prices' && isset($headerMap['stock'])) {
                $raw = trim((string) ($row[$headerMap['stock']] ?? ''));
                if ($raw !== '') {
                    $stockToUpdate = (int) $raw;
                }
            }

            $syncPayload = [
                'sync_source' => DistributorProduct::SYNC_CSV,
                'synced_at' => now(),
            ];

            if (! $existing) {
                $stats['skipped']++;
                $stats['errors'][] = "Строка {$rowNum} ({$sku}): позиция не найдена в номенклатуре";

                continue;
            }

            $updateData = $syncPayload + ['name' => $name];

            if ($brand !== null) {
                $updateData['brand'] = $brand;
            }
            if ($barcode !== null) {
                $updateData['barcode'] = $barcode;
            }
            if ($manufacturerSku !== null) {
                $updateData['manufacturer_sku'] = $manufacturerSku;
            }
            if ($categoryId !== null) {
                $updateData['product_category_id'] = $categoryId;
            }
            if ($unitTypeId !== null) {
                $updateData['unit_type_id'] = $unitTypeId;
            }

            $existingRetailOld = $existing->retail_price;
            $existingPurchaseOld = $existing->purchase_price;

            if ($priceToUpdate !== null) {
                $updateData['retail_price'] = $priceToUpdate;
                $updateData['price_updated_at'] = now();
            }
            if ($purchaseToUpdate !== null) {
                $updateData['purchase_price'] = $purchaseToUpdate;
                $updateData['price_updated_at'] = now();
            }

            $product = $existing;
            $product->update($updateData);

            if ($priceToUpdate !== null) {
                DistributorProductPriceHistory::create([
                    'distributor_product_id' => $product->id,
                    'price_type' => DistributorProductPriceHistory::TYPE_RETAIL,
                    'old_price' => $existingRetailOld,
                    'new_price' => $priceToUpdate,
                    'comment' => 'Импорт CSV',
                    'changed_by_user_id' => $user->id,
                ]);
            }

            if ($purchaseToUpdate !== null && $purchaseToUpdate !== (float) ($existingPurchaseOld ?? 0)) {
                DistributorProductPriceHistory::create([
                    'distributor_product_id' => $product->id,
                    'price_type' => DistributorProductPriceHistory::TYPE_PURCHASE,
                    'old_price' => $existingPurchaseOld,
                    'new_price' => $purchaseToUpdate,
                    'comment' => 'Импорт CSV',
                    'changed_by_user_id' => $user->id,
                ]);
            }

            if ($stockToUpdate !== null && $warehouse) {
                DistributorProductStock::query()->updateOrCreate(
                    [
                        'distributor_product_id' => $product->id,
                        'distributor_warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => $stockToUpdate,
                        'stock_updated_at' => now(),
                        'stock_updated_by_user_id' => $user->id,
                    ],
                );
            } elseif ($stockToUpdate !== null) {
                $stats['errors'][] = "Строка {$rowNum} ({$sku}): остаток не загружен — нет активного склада.";
            }

            DistributorProductLogger::log($product, 'csv_import', 'Обновление из CSV', null, $user);
            $stats['updated']++;
        }

        fclose($handle);

        return $stats;
    }

    private function importXml(string $path, DistributorProfile $profile, string $importType, ?\App\Models\DistributorWarehouse $warehouse, $user): array
    {
        $xml = simplexml_load_file($path);
        $shop = $xml?->shop ?? null;
        if (! $xml || ! $shop || ! isset($shop->offers->offer)) {
            throw new \Exception('Неверный формат XML/YML файла');
        }

        $categories = [];
        if (isset($shop->categories->category)) {
            foreach ($shop->categories->category as $cat) {
                $catId = (string) $cat['id'];
                $catName = trim((string) $cat);
                if ($catName === '') {
                    continue;
                }

                $matched = ProductCategory::query()->where('name', $catName)->first();
                if ($matched) {
                    $categories[$catId] = $matched->id;
                }
            }
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if ($importType !== 'prices' && $warehouse === null) {
            $stats['errors'][] = 'Нет активного склада — остатки из YML не будут загружены.';
        }

        foreach ($shop->offers->offer as $offer) {
            $sku = trim((string) ($offer['id'] ?? $offer->vendorCode ?? ''));
            if ($sku === '') {
                $stats['skipped']++;

                continue;
            }

            $name = trim((string) ($offer->name ?? $offer->model ?? $offer->vendorCode ?? ''));
            if ($name === '') {
                $name = $sku;
            }

            $brand = trim((string) ($offer->vendor ?? $offer->manufacturer ?? $offer->brand ?? ''));
            $brand = $brand !== '' ? $brand : null;

            $barcode = trim((string) ($offer->barcode ?? $offer->ean ?? ''));
            $barcode = $barcode !== '' ? $barcode : null;

            $description = trim((string) ($offer->description ?? ''));
            $description = $description !== '' ? $description : null;

            $categoryId = null;
            $categoryKey = (string) ($offer->categoryId ?? '');
            if ($categoryKey !== '' && isset($categories[$categoryKey])) {
                $categoryId = $categories[$categoryKey];
            }

            $priceToUpdate = null;
            if ($importType !== 'stocks' && isset($offer->price) && (string) $offer->price !== '') {
                $priceToUpdate = (float) $offer->price;
            }

            $stockToUpdate = null;
            if ($importType !== 'prices') {
                if (isset($offer->count)) {
                    $stockToUpdate = (int) $offer->count;
                } elseif (isset($offer->outlets->outlet)) {
                    $maxStock = 0;
                    foreach ($offer->outlets->outlet as $outlet) {
                        $maxStock = max($maxStock, (int) ($outlet['instock'] ?? 0));
                    }
                    $stockToUpdate = $maxStock;
                }
            }

            $existing = DistributorProduct::forDistributor($profile->id)
                ->where('internal_sku', $sku)
                ->first();

            $syncPayload = [
                'sync_source' => DistributorProduct::SYNC_YML,
                'synced_at' => now(),
            ];

            if (! $existing) {
                $stats['skipped']++;
                $stats['errors'][] = "Товар «{$sku}»: не найден в номенклатуре дистрибьютора";

                continue;
            }

            $product = $existing;
            $updateData = $syncPayload + ['name' => $name];

            if ($brand !== null) {
                $updateData['brand'] = $brand;
            }
            if ($barcode !== null) {
                $updateData['barcode'] = $barcode;
            }
            if ($categoryId !== null) {
                $updateData['product_category_id'] = $categoryId;
            }
            if ($description !== null) {
                $updateData['description'] = $description;
                $updateData['short_description'] = $description;
            }

            $oldRetail = $product->retail_price;
            if ($priceToUpdate !== null) {
                $updateData['retail_price'] = $priceToUpdate;
                $updateData['price_updated_at'] = now();
            }

            $product->update($updateData);

            if ($priceToUpdate !== null) {
                DistributorProductPriceHistory::create([
                    'distributor_product_id' => $product->id,
                    'price_type' => DistributorProductPriceHistory::TYPE_RETAIL,
                    'old_price' => $oldRetail,
                    'new_price' => $priceToUpdate,
                    'comment' => 'Импорт YML',
                    'changed_by_user_id' => $user->id,
                ]);
            }

            if ($stockToUpdate !== null && $warehouse) {
                DistributorProductStock::query()->updateOrCreate(
                    [
                        'distributor_product_id' => $product->id,
                        'distributor_warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => $stockToUpdate,
                        'stock_updated_at' => now(),
                        'stock_updated_by_user_id' => $user->id,
                    ],
                );
            } elseif ($stockToUpdate !== null) {
                $stats['errors'][] = "Товар «{$sku}»: остаток не загружен — нет активного склада.";
            }

            DistributorProductLogger::log($product, 'yml_import', 'Обновление из YML', null, $user);
            $stats['updated']++;
        }

        return $stats;
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

<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\ProductRegionalPrice;
use App\Models\ProductStock;
use App\Models\Region;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->manufacturerProfile;

        $query = Product::forManufacturer($profile->id)
            ->with(['category', 'images', 'stocks.warehouse'])
            ->withCount('stocks');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category')) {
            $query->inCategory($request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('needs_update')) {
            $query->needsUpdate();
        }

        if ($request->filled('has_stock')) {
            if ($request->has_stock === 'yes') {
                $query->whereHas('stocks', fn($q) => $q->where('quantity', '>', 0));
            } else {
                $query->whereDoesntHave('stocks', fn($q) => $q->where('quantity', '>', 0));
            }
        }

        $sortField = $request->get('sort', 'updated_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $products = $query->paginate(25)->withQueryString();

        $categories = ProductCategory::active()->orderBy('sort_order')->get();

        return view('manufacturer.products.index', compact('products', 'categories'));
    }

    public function create(Request $request): View
    {
        $profile = $request->user()->manufacturerProfile;

        $categories = ProductCategory::active()->orderBy('sort_order')->get();
        $unitTypes = UnitType::active()->orderBy('sort_order')->get();
        $warehouses = $profile->warehouses()->active()->get();
        $regions = Region::active()->orderBy('sort_order')->get();
        $attributes = ProductAttribute::active()
            ->forCategory($request->old('category_id'))
            ->orderBy('sort_order')
            ->get();

        return view('manufacturer.products.form', [
            'product' => null,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'warehouses' => $warehouses,
            'regions' => $regions,
            'attributes' => $attributes,
            'tab' => $request->get('tab', 'basic'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $validated = $this->validateProduct($request);

        DB::beginTransaction();
        try {
            $product = Product::create([
                'manufacturer_profile_id' => $profile->id,
                ...$validated,
                'price_updated_at' => now(),
            ]);

            $this->handleImages($request, $product);
            $this->handleStocks($request, $product);
            $this->handleRegionalPrices($request, $product);
            $this->handleAttributes($request, $product);
            $this->handleRegions($request, $product);
            $this->handleAdditionalCategories($request, $product);
            $this->handleDocuments($request, $product);

            DB::commit();

            return redirect()
                ->route('manufacturer.products.edit', $product)
                ->with('success', 'Товар успешно создан');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        $profile = $request->user()->manufacturerProfile;

        $product->load([
            'category',
            'additionalCategories',
            'unitType',
            'images',
            'stocks.warehouse',
            'regionalPrices.region',
            'attributeValues.attribute',
            'availableRegions',
            'documents',
        ]);

        $categories = ProductCategory::active()->orderBy('sort_order')->get();
        $unitTypes = UnitType::active()->orderBy('sort_order')->get();
        $warehouses = $profile->warehouses()->active()->get();
        $regions = Region::active()->orderBy('sort_order')->get();
        $attributes = ProductAttribute::active()
            ->forCategory($product->category_id)
            ->orderBy('sort_order')
            ->get();

        return view('manufacturer.products.form', [
            'product' => $product,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'warehouses' => $warehouses,
            'regions' => $regions,
            'attributes' => $attributes,
            'tab' => $request->get('tab', 'basic'),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $this->validateProduct($request, $product);

        $priceChanged = $product->base_price != ($validated['base_price'] ?? $product->base_price);

        DB::beginTransaction();
        try {
            $product->update([
                ...$validated,
                'is_modified' => $product->isSynced(),
                'price_updated_at' => $priceChanged ? now() : $product->price_updated_at,
            ]);

            $this->handleImages($request, $product);
            $this->handleStocks($request, $product);
            $this->handleRegionalPrices($request, $product);
            $this->handleAttributes($request, $product);
            $this->handleRegions($request, $product);
            $this->handleAdditionalCategories($request, $product);
            $this->handleDocuments($request, $product);

            DB::commit();

            return redirect()
                ->route('manufacturer.products.edit', ['product' => $product, 'tab' => $request->get('tab', 'basic')])
                ->with('success', 'Изменения сохранены');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        foreach ($product->documents as $document) {
            Storage::disk('public')->delete($document->file_path);
        }

        $product->delete();

        return redirect()
            ->route('manufacturer.products.index')
            ->with('success', 'Товар удалён');
    }

    public function publish(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if (!$product->canBePublished()) {
            return back()->with('error', 'Невозможно опубликовать товар: заполните обязательные поля (название, цена, категория) и добавьте остатки.');
        }

        $product->update([
            'status' => Product::STATUS_ACTIVE,
            'published_at' => $product->published_at ?? now(),
            'show_in_catalog' => true,
        ]);

        return back()->with('success', 'Товар опубликован');
    }

    public function hide(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->update([
            'status' => Product::STATUS_HIDDEN,
            'show_in_catalog' => false,
        ]);

        return back()->with('success', 'Товар скрыт');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $validated = $request->validate([
            'action' => 'required|in:delete,publish,hide,change_category,change_price,apply_discount,update_stock',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'category_id' => 'required_if:action,change_category|nullable|exists:product_categories,id',
            'price' => 'required_if:action,change_price|nullable|numeric|min:0',
            'discount_percent' => 'required_if:action,apply_discount|nullable|numeric|min:0|max:100',
            'stock_quantity' => 'required_if:action,update_stock|nullable|integer|min:0',
            'warehouse_id' => 'required_if:action,update_stock|nullable|exists:warehouses,id',
        ]);

        $products = Product::whereIn('id', $validated['product_ids'])
            ->where('manufacturer_profile_id', $profile->id)
            ->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'Товары не найдены');
        }

        DB::beginTransaction();
        try {
            switch ($validated['action']) {
                case 'delete':
                    foreach ($products as $product) {
                        foreach ($product->images as $image) {
                            Storage::disk('public')->delete($image->path);
                        }
                        foreach ($product->documents as $document) {
                            Storage::disk('public')->delete($document->file_path);
                        }
                        $product->delete();
                    }
                    $message = 'Товары удалены';
                    break;

                case 'publish':
                    $published = 0;
                    foreach ($products as $product) {
                        if ($product->canBePublished()) {
                            $product->update([
                                'status' => Product::STATUS_ACTIVE,
                                'published_at' => $product->published_at ?? now(),
                                'show_in_catalog' => true,
                            ]);
                            $published++;
                        }
                    }
                    $message = "Опубликовано товаров: {$published}";
                    break;

                case 'hide':
                    Product::whereIn('id', $products->pluck('id'))->update([
                        'status' => Product::STATUS_HIDDEN,
                        'show_in_catalog' => false,
                    ]);
                    $message = 'Товары скрыты';
                    break;

                case 'change_category':
                    Product::whereIn('id', $products->pluck('id'))->update([
                        'category_id' => $validated['category_id'],
                    ]);
                    $message = 'Категория изменена';
                    break;

                case 'change_price':
                    Product::whereIn('id', $products->pluck('id'))->update([
                        'base_price' => $validated['price'],
                        'price_updated_at' => now(),
                    ]);
                    $message = 'Цена установлена';
                    break;

                case 'apply_discount':
                    $discount = $validated['discount_percent'] / 100;
                    foreach ($products as $product) {
                        if ($product->base_price) {
                            $product->update([
                                'base_price' => round($product->base_price * (1 - $discount), 2),
                                'price_updated_at' => now(),
                            ]);
                        }
                    }
                    $message = 'Скидка применена';
                    break;

                case 'update_stock':
                    foreach ($products as $product) {
                        ProductStock::updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'warehouse_id' => $validated['warehouse_id'],
                            ],
                            [
                                'quantity' => $validated['stock_quantity'],
                                'stock_updated_at' => now(),
                            ]
                        );
                    }
                    $message = 'Остатки обновлены';
                    break;

                default:
                    $message = 'Операция выполнена';
            }

            DB::commit();
            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ошибка при выполнении операции');
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $query = Product::forManufacturer($profile->id)
            ->with(['category', 'unitType', 'stocks.warehouse']);

        if ($request->get('filter') === 'active') {
            $query->active();
        } elseif ($request->get('filter') === 'modified') {
            $query->where('is_modified', true);
        }

        $products = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'Артикул',
                'Наименование',
                'Категория',
                'Цена',
                'Остаток',
                'Единица измерения',
                'Статус',
                'Описание',
            ], ';');

            foreach ($products as $product) {
                fputcsv($file, [
                    $product->sku,
                    $product->name,
                    $product->category?->name ?? '',
                    $product->base_price,
                    $product->total_stock,
                    $product->unitType?->short_name ?? 'шт.',
                    $product->statusLabel(),
                    strip_tags($product->description ?? ''),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importForm(Request $request): View
    {
        return view('manufacturer.products.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xml,yml|max:10240',
            'format' => 'required|in:csv,yml',
        ]);

        $profile = $request->user()->manufacturerProfile;
        $file = $request->file('file');
        $format = $request->format;

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            if ($format === 'csv') {
                $stats = $this->importCsv($file, $profile, $stats);
            } elseif ($format === 'yml') {
                $stats = $this->importYml($file, $profile, $stats);
            }

            DB::commit();

            $message = "Импорт завершён. Создано: {$stats['created']}, обновлено: {$stats['updated']}, пропущено: {$stats['skipped']}";

            if (!empty($stats['errors'])) {
                return redirect()
                    ->route('manufacturer.products.import')
                    ->with('warning', $message)
                    ->with('import_errors', array_slice($stats['errors'], 0, 20));
            }

            return redirect()
                ->route('manufacturer.products.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ошибка импорта: ' . $e->getMessage());
        }
    }

    public function deleteImage(Request $request, ProductImage $image): RedirectResponse
    {
        $product = $image->product;
        $this->authorizeProduct($request, $product);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        if ($image->is_primary) {
            $product->images()->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Изображение удалено');
    }

    public function setPrimaryImage(Request $request, ProductImage $image): RedirectResponse
    {
        $product = $image->product;
        $this->authorizeProduct($request, $product);

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', 'Основное изображение установлено');
    }

    public function deleteDocument(Request $request, ProductDocument $document): RedirectResponse
    {
        $product = $document->product;
        $this->authorizeProduct($request, $product);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Документ удалён');
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $skuRule = 'required|string|max:100';
        if ($product) {
            $skuRule .= '|unique:products,sku,' . $product->id . ',id,manufacturer_profile_id,' . $product->manufacturer_profile_id;
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'sku' => $skuRule,
            'category_id' => 'nullable|exists:product_categories,id',
            'additional_category_ids' => 'nullable|array',
            'additional_category_ids.*' => 'exists:product_categories,id',
            'unit_type_id' => 'nullable|exists:unit_types,id',
            'description' => 'nullable|string|max:2000',
            'video_url' => 'nullable|url|max:500',
            'min_order_quantity' => 'nullable|integer|min:1',
            'base_price' => 'nullable|numeric|min:0',
            'manufacturer_sku' => 'nullable|string|max:100',
            'distributor_sku' => 'nullable|string|max:100',
            'ean' => 'nullable|string|max:20',
            'barcode' => 'nullable|string|max:50',
            'expiry_date' => 'nullable|date',
            'storage_conditions' => 'nullable|string|max:500',
            'transport_conditions' => 'nullable|string|max:500',
            'instruction_url' => 'nullable|url|max:500',
            'status' => 'nullable|in:active,hidden,draft',
            'show_in_catalog' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);
    }

    private function handleImages(Request $request, Product $product): void
    {
        if ($request->hasFile('images')) {
            $hasImages = $product->images()->exists();

            foreach ($request->file('images') as $index => $file) {
                $path = $file->store('products/images', 'public');

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'is_primary' => !$hasImages && $index === 0,
                    'sort_order' => $product->images()->max('sort_order') + 1,
                ]);

                $hasImages = true;
            }
        }
    }

    private function handleStocks(Request $request, Product $product): void
    {
        if ($request->has('stocks')) {
            foreach ($request->stocks as $warehouseId => $data) {
                if (!empty($data['quantity']) || $data['quantity'] === '0' || $data['quantity'] === 0) {
                    ProductStock::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouseId,
                        ],
                        [
                            'quantity' => (int) $data['quantity'],
                            'stock_updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function handleRegionalPrices(Request $request, Product $product): void
    {
        if ($request->has('regional_prices')) {
            $product->regionalPrices()->delete();

            foreach ($request->regional_prices as $regionId => $price) {
                if (!empty($price)) {
                    ProductRegionalPrice::create([
                        'product_id' => $product->id,
                        'region_id' => $regionId,
                        'price' => $price,
                    ]);
                }
            }
        }
    }

    private function handleAttributes(Request $request, Product $product): void
    {
        if ($request->has('attributes')) {
            $product->attributeValues()->delete();

            foreach ($request->attributes as $attributeId => $value) {
                if (!empty($value) || $value === '0') {
                    ProductAttributeValue::create([
                        'product_id' => $product->id,
                        'product_attribute_id' => $attributeId,
                        'value' => $value,
                    ]);
                }
            }
        }
    }

    private function handleRegions(Request $request, Product $product): void
    {
        if ($request->has('available_regions')) {
            $product->availableRegions()->sync($request->available_regions);
        } else {
            $product->availableRegions()->detach();
        }
    }

    private function handleAdditionalCategories(Request $request, Product $product): void
    {
        $ids = $request->input('additional_category_ids', []);
        $mainId = $product->category_id;
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $mainId));
        $product->additionalCategories()->sync($ids);
    }

    private function handleDocuments(Request $request, Product $product): void
    {
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $doc) {
                $docName = $request->input('document_names.' . array_search($doc, $request->file('documents')), $doc->getClientOriginalName());
                $docType = $request->input('document_types.' . array_search($doc, $request->file('documents')), 'other');

                $path = $doc->store('products/documents', 'public');

                ProductDocument::create([
                    'product_id' => $product->id,
                    'name' => $docName,
                    'type' => $docType,
                    'file_path' => $path,
                    'original_name' => $doc->getClientOriginalName(),
                    'mime_type' => $doc->getMimeType(),
                    'file_size' => $doc->getSize(),
                ]);
            }
        }
    }

    private function importCsv($file, $profile, array $stats): array
    {
        $handle = fopen($file->getPathname(), 'r');

        $header = fgetcsv($handle, 0, ';');
        $header = array_map(fn($h) => mb_strtolower(trim($h)), $header);

        $mapping = [
            'артикул' => 'sku',
            'sku' => 'sku',
            'наименование' => 'name',
            'название' => 'name',
            'name' => 'name',
            'категория' => 'category',
            'category' => 'category',
            'цена' => 'base_price',
            'price' => 'base_price',
            'остаток' => 'stock',
            'stock' => 'stock',
            'описание' => 'description',
            'description' => 'description',
        ];

        $headerMap = [];
        foreach ($header as $index => $col) {
            if (isset($mapping[$col])) {
                $headerMap[$mapping[$col]] = $index;
            }
        }

        if (!isset($headerMap['sku']) || !isset($headerMap['name'])) {
            throw new \Exception('Файл должен содержать колонки "Артикул" и "Наименование"');
        }

        $row = 1;
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row++;

            $sku = trim($data[$headerMap['sku']] ?? '');
            $name = trim($data[$headerMap['name']] ?? '');

            if (empty($sku) || empty($name)) {
                $stats['skipped']++;
                $stats['errors'][] = "Строка {$row}: пустой артикул или название";
                continue;
            }

            $productData = [
                'name' => $name,
                'sku' => $sku,
                'manufacturer_profile_id' => $profile->id,
            ];

            if (isset($headerMap['base_price']) && !empty($data[$headerMap['base_price']])) {
                $productData['base_price'] = (float) str_replace([' ', ','], ['', '.'], $data[$headerMap['base_price']]);
                $productData['price_updated_at'] = now();
            }

            if (isset($headerMap['description'])) {
                $productData['description'] = $data[$headerMap['description']] ?? null;
            }

            if (isset($headerMap['category']) && !empty($data[$headerMap['category']])) {
                $categoryName = trim($data[$headerMap['category']]);
                $category = ProductCategory::where('name', $categoryName)->first();
                if ($category) {
                    $productData['category_id'] = $category->id;
                }
            }

            $existing = Product::where('manufacturer_profile_id', $profile->id)
                ->where('sku', $sku)
                ->first();

            if ($existing) {
                $existing->update($productData);
                $stats['updated']++;
            } else {
                $productData['sync_source'] = 'csv';
                $productData['synced_at'] = now();
                $existing = Product::create($productData);
                $stats['created']++;
            }

            if (isset($headerMap['stock']) && !empty($data[$headerMap['stock']])) {
                $warehouse = $profile->warehouses()->active()->first();
                if ($warehouse) {
                    ProductStock::updateOrCreate(
                        [
                            'product_id' => $existing->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => (int) $data[$headerMap['stock']],
                            'stock_updated_at' => now(),
                        ]
                    );
                }
            }
        }

        fclose($handle);

        return $stats;
    }

    private function importYml($file, $profile, array $stats): array
    {
        $xml = simplexml_load_file($file->getPathname());

        if (!$xml || !isset($xml->shop->offers->offer)) {
            throw new \Exception('Неверный формат YML файла');
        }

        $categories = [];
        if (isset($xml->shop->categories->category)) {
            foreach ($xml->shop->categories->category as $cat) {
                $catId = (string) $cat['id'];
                $catName = (string) $cat;
                $categories[$catId] = ProductCategory::firstOrCreate(
                    ['name' => $catName],
                    ['slug' => \Illuminate\Support\Str::slug($catName), 'is_active' => true]
                );
            }
        }

        foreach ($xml->shop->offers->offer as $offer) {
            $sku = (string) ($offer['id'] ?? $offer->vendorCode ?? '');
            $name = (string) ($offer->name ?? '');

            if (empty($sku) || empty($name)) {
                $stats['skipped']++;
                continue;
            }

            $productData = [
                'name' => $name,
                'sku' => $sku,
                'manufacturer_profile_id' => $profile->id,
                'description' => (string) ($offer->description ?? null),
                'base_price' => isset($offer->price) ? (float) $offer->price : null,
                'price_updated_at' => isset($offer->price) ? now() : null,
            ];

            if (isset($offer->categoryId) && isset($categories[(string) $offer->categoryId])) {
                $productData['category_id'] = $categories[(string) $offer->categoryId]->id;
            }

            $existing = Product::where('manufacturer_profile_id', $profile->id)
                ->where('sku', $sku)
                ->first();

            if ($existing) {
                $existing->update($productData);
                $stats['updated']++;
            } else {
                $productData['sync_source'] = 'yml';
                $productData['synced_at'] = now();
                Product::create($productData);
                $stats['created']++;
            }
        }

        return $stats;
    }

    public function catalog(Request $request, ?ProductCategory $category = null): View
    {
        $profile = $request->user()->manufacturerProfile;
        $categoryTree = ProductCategory::getTree(true, $profile->id);
        $attributeFilters = $this->parseAttributeFilters($request);
        $filterableAttributes = $category
            ? ProductAttribute::active()->forCategory($category->id)->filterable()->orderBy('sort_order')->get()
            : collect();
        $query = Product::forManufacturer($profile->id)
            ->with(['category', 'images'])
            ->whereNotNull('category_id')
            ->published();
        if ($category) {
            $query->inCategory($category->id);
        }
        $query->withAttributeFilters($attributeFilters);
        $products = $query->orderBy('name')->paginate(24)->withQueryString();
        return view('manufacturer.catalog.index', [
            'categoryTree' => $categoryTree,
            'products' => $products,
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'filterableAttributes' => $filterableAttributes,
            'appliedFilters' => $attributeFilters,
        ]);
    }

    public function catalogProducts(Request $request): Response
    {
        $profile = $request->user()->manufacturerProfile;
        $categorySlugOrId = $request->get('category');
        $category = $this->resolveCategoryFromRequest($categorySlugOrId);
        $attributeFilters = $this->parseAttributeFilters($request);
        $filterableAttributes = $category
            ? ProductAttribute::active()->forCategory($category->id)->filterable()->orderBy('sort_order')->get()
            : collect();
        $query = Product::forManufacturer($profile->id)
            ->with(['category', 'images'])
            ->whereNotNull('category_id')
            ->published();
        if ($category) {
            $query->inCategory($category->id);
        }
        $query->withAttributeFilters($attributeFilters);
        $products = $query->orderBy('name')->paginate(24)->withQueryString();
        return response()->view('manufacturer.catalog._products', [
            'products' => $products,
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'filterableAttributes' => $filterableAttributes,
            'appliedFilters' => $attributeFilters,
        ])->header('Cache-Control', 'no-store');
    }

    /** Разрешает категорию по slug или id из query (для AJAX/форм). */
    private function resolveCategoryFromRequest(?string $value): ?ProductCategory
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return ProductCategory::active()->find((int) $value);
        }
        return ProductCategory::active()->where('slug', $value)->first();
    }

    /** Парсит параметры фильтров атрибутов из запроса (attr[id]=value или attr[id][]=value) */
    private function parseAttributeFilters(Request $request): array
    {
        $attr = $request->input('attr', $request->input('attributes', []));
        if (!is_array($attr)) {
            return [];
        }
        $out = [];
        foreach ($attr as $id => $value) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            if (is_array($value)) {
                $value = array_filter(array_map('trim', $value));
                if ($value !== []) {
                    $out[$id] = $value;
                }
            } else {
                $value = trim((string) $value);
                if ($value !== '') {
                    $out[$id] = $value;
                }
            }
        }
        return $out;
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        if ($product->manufacturer_profile_id !== $request->user()->manufacturerProfile?->id) {
            abort(403);
        }
    }
}

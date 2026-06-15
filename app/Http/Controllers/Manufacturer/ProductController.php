<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Concerns\BuildsCatalogListing;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\ProductRegionalPrice;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\UnitType;
use App\Services\Catalog\CatalogQueryService;
use App\Services\CurrentRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use BuildsCatalogListing;

    public function index(Request $request): View
    {
        $profile = $request->user()->manufacturerProfile;

        $query = Product::forManufacturer($profile->id)
            ->with(['category', 'images', 'stocks.warehouse'])
            ->withCount(['stocks']);

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim()->toString());
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
                $query->withAvailableStock();
            } else {
                $query->withoutAvailableStock();
            }
        }

        $this->applyIndexSorting($query, $request);

        $products = $query->paginate(25)->withQueryString();

        $trashedMatches = collect();
        if ($request->filled('search') && $products->isEmpty()) {
            $trashedMatches = Product::onlyTrashed()
                ->forManufacturer($profile->id)
                ->search($request->string('search')->trim()->toString())
                ->orderByDesc('deleted_at')
                ->limit(5)
                ->get(['id', 'name', 'sku', 'deleted_at']);
        }

        $categoryTree = ProductCategory::assignableTree();
        $categories = ProductCategory::active()->assignableForProducts()->orderBy('sort_order')->get();

        return view('manufacturer.products.index', [
            'products' => $products,
            'categoryTree' => $categoryTree,
            'categories' => $categories,
            'trashedMatches' => $trashedMatches,
            'productPriceStaleDays' => Product::priceStaleDays(),
        ]);
    }

    public function create(Request $request): View
    {
        $profile = $request->user()->manufacturerProfile;

        $categoryTree = ProductCategory::assignableTree();
        $categories = ProductCategory::active()->assignableForProducts()->orderBy('sort_order')->get();
        $unitTypes = UnitType::active()->orderBy('name')->get();
        $warehouses = $profile->warehouses()->active()->get();
        $regions = $profile->regions()
            ->active()
            ->orderBy('name')
            ->get();
        $attributes = ProductAttribute::active()
            ->forCategory($request->old('category_id'))
            ->orderBy('sort_order')
            ->get();

        return view('manufacturer.products.form', [
            'product' => null,
            'categoryTree' => $categoryTree,
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
            'analogs:id,name,sku,manufacturer_profile_id',
            'analogOf:id,name,sku,manufacturer_profile_id',
        ]);

        $categoryTree = ProductCategory::assignableTree();
        $categories = ProductCategory::active()->assignableForProducts()->orderBy('sort_order')->get();
        $unitTypes = UnitType::active()->orderBy('name')->get();
        $warehouses = $profile->warehouses()->active()->get();
        $regions = $profile->regions()
            ->active()
            ->orderBy('name')
            ->get();
        $attributes = ProductAttribute::active()
            ->forCategory($product->category_id)
            ->orderBy('sort_order')
            ->get();

        return view('manufacturer.products.form', [
            'product' => $product,
            'categoryTree' => $categoryTree,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'warehouses' => $warehouses,
            'regions' => $regions,
            'attributes' => $attributes,
            'tab' => $request->get('tab', 'basic'),
            'selectedAnalogs' => Product::query()
                ->whereIn('id', $product->allAnalogIds())
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'manufacturer_profile_id']),
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
            $this->handleAnalogs($request, $product);

            if ($request->get('tab') === 'publication') {
                $product->update([
                    'status' => $validated['status'] ?? $product->status,
                    'published_at' => $validated['published_at'] ?? $product->published_at,
                    'show_in_catalog' => $request->boolean('show_in_catalog'),
                    'mark_is_new' => $request->boolean('mark_is_new'),
                    'mark_on_sale' => $request->boolean('mark_on_sale'),
                    'mark_discontinued' => $request->boolean('mark_discontinued'),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('manufacturer.products.edit', ['product' => $product, 'tab' => $request->get('tab', 'basic')])
                ->with('success', 'Изменения сохранены');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function analogSearch(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $compact = preg_replace('/\s+/u', '', $search);

        $items = Product::query()
            ->where('id', '!=', $product->id)
            ->compatibleWithProduct($product)
            ->search($search)
            ->orderByRaw(
                'CASE WHEN sku = ? OR REPLACE(COALESCE(sku, ""), " ", "") = ? THEN 0 ELSE 1 END',
                [$search, $compact]
            )
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'sku', 'manufacturer_profile_id']);

        return response()->json([
            'data' => $items,
        ]);
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
            ->with('success', 'Товар удалён. Его можно восстановить через поиск по артикулу.');
    }

    public function restore(Request $request, int $productId): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $product = Product::onlyTrashed()
            ->forManufacturer($profile->id)
            ->findOrFail($productId);

        $product->restore();

        return redirect()
            ->route('manufacturer.products.edit', $product)
            ->with('success', 'Товар восстановлен. Проверьте данные и сохраните изменения.');
    }

    public function publish(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if (! $product->canBePublished()) {
            return back()->with('error', 'Невозможно опубликовать товар: заполните обязательные поля.');
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
            'category_id' => [
                'required_if:action,change_category',
                'nullable',
                'exists:product_categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $cat = ProductCategory::query()->find((int) $value);
                    if ($cat && ! $cat->accepts_products) {
                        $fail('Нельзя назначить товары в категорию-контейнер.');
                    }
                },
            ],
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
                                'stock_updated_by_user_id' => $request->user()->id,
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

        if ($request->filled('search')) {
            $query->search($request->string('search')->trim()->toString());
        }

        if ($request->get('filter') === 'active') {
            $query->active();
        } elseif ($request->get('filter') === 'modified') {
            $query->where('is_modified', true);
        }

        $products = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products_'.date('Y-m-d').'.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

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

            if (! empty($stats['errors'])) {
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

            return back()->with('error', 'Ошибка импорта: '.$e->getMessage());
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
            $skuRule .= '|unique:products,sku,'.$product->id.',id,manufacturer_profile_id,'.$product->manufacturer_profile_id;
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'sku' => $skuRule,
            'category_id' => [
                'required',
                'exists:product_categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $cat = ProductCategory::query()->find((int) $value);
                    if ($cat && ! $cat->accepts_products) {
                        $fail('Эта категория только для подкатегорий; выберите конечную категорию, куда допускаются товары.');
                    }
                },
            ],
            'additional_category_ids' => 'nullable|array',
            'additional_category_ids.*' => [
                'exists:product_categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $cat = ProductCategory::query()->find((int) $value);
                    if ($cat && ! $cat->accepts_products) {
                        $fail('Дополнительная категория не может быть контейнером без товаров.');
                    }
                },
            ],
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
            'mark_is_new' => 'nullable|boolean',
            'mark_on_sale' => 'nullable|boolean',
            'mark_discontinued' => 'nullable|boolean',
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
                    'is_primary' => ! $hasImages && $index === 0,
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
                if (! empty($data['quantity']) || $data['quantity'] === '0' || $data['quantity'] === 0) {
                    ProductStock::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouseId,
                        ],
                        [
                            'quantity' => (int) $data['quantity'],
                            'stock_updated_at' => now(),
                            'stock_updated_by_user_id' => $request->user()->id,
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
                if (! empty($price)) {
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
            $this->syncSystemAttributes($request, $product);
        }

        $this->handleCustomAttributes($request, $product);
    }

    private function syncSystemAttributes(Request $request, Product $product): void
    {
        $raw = $request->input('attributes', []);
        if (! is_array($raw)) {
            return;
        }

        $systemAttributeIds = ProductAttribute::query()
            ->active()
            ->forCategory($product->category_id)
            ->pluck('id');

        $product->attributeValues()
            ->whereIn('product_attribute_id', $systemAttributeIds)
            ->delete();

        $attributesById = ProductAttribute::query()
            ->whereIn('id', array_map(static fn ($k): int => (int) $k, array_keys($raw)))
            ->get()
            ->keyBy('id');

        foreach ($raw as $attributeId => $value) {
            $attributeId = (int) $attributeId;
            if ($attributeId <= 0) {
                continue;
            }
            $attrModel = $attributesById->get($attributeId);
            if (! $attrModel instanceof ProductAttribute) {
                continue;
            }

            if ($attrModel->type === ProductAttribute::TYPE_RANGE) {
                if (! is_array($value)) {
                    continue;
                }
                $min = isset($value['min']) ? trim((string) $value['min']) : '';
                $max = isset($value['max']) ? trim((string) $value['max']) : '';
                if ($min === '' && $max === '') {
                    continue;
                }
                ProductAttributeValue::create([
                    'product_id' => $product->id,
                    'product_attribute_id' => $attributeId,
                    'value' => json_encode(['min' => $min, 'max' => $max], JSON_UNESCAPED_UNICODE),
                ]);

                continue;
            }

            if (! empty($value) || $value === '0' || $value === 0) {
                $stored = is_array($value)
                    ? implode(',', array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== '')))
                    : (string) $value;
                if ($stored !== '' || $value === '0' || $value === 0) {
                    ProductAttributeValue::create([
                        'product_id' => $product->id,
                        'product_attribute_id' => $attributeId,
                        'value' => $stored,
                    ]);
                }
            }
        }
    }

    private function handleCustomAttributes(Request $request, Product $product): void
    {
        if (! $request->has('_custom_attributes_present')) {
            return;
        }

        $raw = $request->input('custom_attributes', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        $existingCustomIds = ProductAttribute::query()
            ->where('product_id', $product->id)
            ->pluck('id');

        if ($existingCustomIds->isNotEmpty()) {
            $product->attributeValues()
                ->whereIn('product_attribute_id', $existingCustomIds)
                ->delete();
            ProductAttribute::query()->whereIn('id', $existingCustomIds)->delete();
        }

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['key'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $attribute = ProductAttribute::create([
                'product_id' => $product->id,
                'product_category_id' => null,
                'name' => $name,
                'slug' => $this->uniqueCustomAttributeSlug($product, $name),
                'type' => ProductAttribute::TYPE_TEXT,
                'is_filterable' => false,
                'is_required' => false,
                'is_active' => true,
            ]);

            ProductAttributeValue::create([
                'product_id' => $product->id,
                'product_attribute_id' => $attribute->id,
                'value' => $value,
            ]);
        }
    }

    private function uniqueCustomAttributeSlug(Product $product, string $name): string
    {
        $base = Str::slug(Str::limit($name, 80, ''));
        if ($base === '') {
            $base = 'attr';
        }

        $slug = 'p'.$product->id.'-'.$base;
        $counter = 0;

        while (ProductAttribute::query()->where('product_id', $product->id)->where('slug', $slug)->exists()) {
            $counter++;
            $slug = 'p'.$product->id.'-'.$base.'-'.$counter;
        }

        return $slug;
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
                $docName = $request->input('document_names.'.array_search($doc, $request->file('documents')), $doc->getClientOriginalName());
                $docType = $request->input('document_types.'.array_search($doc, $request->file('documents')), 'other');

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

    private function handleAnalogs(Request $request, Product $product): void
    {
        $validated = $request->validate([
            'analog_ids' => ['nullable', 'array'],
            'analog_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $analogIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            $validated['analog_ids'] ?? []
        )));
        $analogIds = array_values(array_filter($analogIds, fn (int $id): bool => $id !== (int) $product->id));

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
    }

    private function importCsv($file, $profile, array $stats): array
    {
        $handle = $this->openCsvImportStream($file);

        $header = fgetcsv($handle, 0, ';', escape: '\\');
        if ($header === false) {
            throw new \Exception('Файл пуст или неверный формат CSV');
        }

        $headerMap = $this->buildCsvHeaderMap($header);

        if (! isset($headerMap['sku']) || ! isset($headerMap['name'])) {
            throw new \Exception('Файл должен содержать колонки «Артикул» и «Наименование» (или SKU / Name)');
        }

        $row = 1;
        while (($data = fgetcsv($handle, 0, ';', escape: '\\')) !== false) {
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

            if (isset($headerMap['base_price']) && ! empty($data[$headerMap['base_price']])) {
                $productData['base_price'] = (float) str_replace([' ', ','], ['', '.'], $data[$headerMap['base_price']]);
                $productData['price_updated_at'] = now();
            }

            if (isset($headerMap['description'])) {
                $productData['description'] = $data[$headerMap['description']] ?? null;
            }

            if (isset($headerMap['category']) && ! empty($data[$headerMap['category']])) {
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

            if (isset($headerMap['stock']) && ! empty($data[$headerMap['stock']])) {
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
                            'stock_updated_by_user_id' => $profile->user_id,
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
        $shop = $xml->shop ?? null;

        if (! $xml || ! $shop || ! isset($shop->offers->offer)) {
            throw new \Exception('Неверный формат YML файла');
        }

        $categories = [];
        if (isset($shop->categories->category)) {
            foreach ($shop->categories->category as $cat) {
                $catId = (string) $cat['id'];
                $catName = trim((string) $cat);
                if ($catName === '') {
                    continue;
                }
                $matched = ProductCategory::query()
                    ->active()
                    ->where('shown_in_customer_catalog', true)
                    ->where('name', $catName)
                    ->first();
                if ($matched) {
                    $categories[$catId] = $matched;
                }
            }
        }

        foreach ($shop->offers->offer as $offer) {
            $sku = trim((string) ($offer['id'] ?? $offer->vendorCode ?? ''));

            if ($sku === '') {
                $stats['skipped']++;
                $stats['errors'][] = 'Пропущен offer без артикула (id / vendorCode)';

                continue;
            }

            $name = $this->extractYmlOfferName($offer);

            $existing = Product::where('manufacturer_profile_id', $profile->id)
                ->where('sku', $sku)
                ->first();

            if ($name === '' && ! $existing) {
                $stats['skipped']++;
                $stats['errors'][] = "Товар «{$sku}»: в YML нет названия, товар не найден в каталоге (сначала загрузите CSV)";

                continue;
            }

            $productData = [
                'manufacturer_profile_id' => $profile->id,
            ];

            if ($name !== '') {
                $productData['name'] = $name;
            }

            $description = trim((string) ($offer->description ?? ''));
            if ($description !== '') {
                $productData['description'] = $description;
            }

            if (isset($offer->price)) {
                $productData['base_price'] = (float) $offer->price;
                $productData['price_updated_at'] = now();
            }

            if (isset($offer->categoryId) && isset($categories[(string) $offer->categoryId])) {
                $productData['category_id'] = $categories[(string) $offer->categoryId]->id;
            }

            if ($existing) {
                $existing->update($productData);
                $product = $existing;
                $stats['updated']++;
            } else {
                $productData['name'] = $name;
                $productData['sku'] = $sku;
                $productData['sync_source'] = 'yml';
                $productData['synced_at'] = now();
                $product = Product::create($productData);
                $stats['created']++;
            }

            $stockQty = $this->extractYmlOfferStock($offer);
            if ($stockQty !== null) {
                $warehouse = $profile->warehouses()->active()->first();
                if ($warehouse) {
                    ProductStock::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => $stockQty,
                            'stock_updated_at' => now(),
                            'stock_updated_by_user_id' => $profile->user_id,
                        ]
                    );
                }
            }
        }

        return $stats;
    }

    /**
     * @return resource
     */
    private function openCsvImportStream($file)
    {
        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            throw new \Exception('Не удалось прочитать CSV файл');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (! mb_check_encoding($content, 'UTF-8')) {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'CP1251', 'ISO-8859-5'], true);
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'Windows-1251');
        }

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>
     */
    private function buildCsvHeaderMap(array $header): array
    {
        $aliases = [
            'sku' => ['артикул', 'sku', 'код', 'code', 'vendorcode'],
            'name' => ['наименование', 'название', 'name', 'title', 'товар'],
            'category' => ['категория', 'category'],
            'base_price' => ['цена', 'price', 'base_price', 'базовая цена'],
            'stock' => ['остаток', 'stock', 'quantity', 'количество'],
            'description' => ['описание', 'description'],
        ];

        $headerMap = [];

        foreach ($header as $index => $col) {
            $normalized = $this->normalizeCsvHeaderColumn((string) $col);

            foreach ($aliases as $field => $keys) {
                if (isset($headerMap[$field])) {
                    continue;
                }

                foreach ($keys as $key) {
                    if ($normalized === $key || str_starts_with($normalized, $key.' ') || str_contains($normalized, $key)) {
                        $headerMap[$field] = $index;

                        break 2;
                    }
                }
            }
        }

        return $headerMap;
    }

    private function normalizeCsvHeaderColumn(string $col): string
    {
        $col = mb_strtolower(trim($col));
        $col = preg_replace('/^\x{FEFF}/u', '', $col) ?? $col;

        if (str_contains($col, '/')) {
            $col = trim(explode('/', $col, 2)[0]);
        }

        return trim($col);
    }

    private function extractYmlOfferName(\SimpleXMLElement $offer): string
    {
        foreach (['name', 'model', 'vendorCode'] as $field) {
            $value = trim((string) ($offer->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function extractYmlOfferStock(\SimpleXMLElement $offer): ?int
    {
        if (isset($offer->count)) {
            return (int) $offer->count;
        }

        if (! isset($offer->outlets->outlet)) {
            return null;
        }

        $maxStock = 0;

        foreach ($offer->outlets->outlet as $outlet) {
            $maxStock = max($maxStock, (int) ($outlet['instock'] ?? 0));
        }

        return $maxStock;
    }

    public function catalog(Request $request, ?ProductCategory $category = null): View
    {
        $catalogRole = app(CurrentRoleService::class)->get($request->user());
        if ($category !== null && ! $category->isShownInCatalogForRole($catalogRole)) {
            abort(404);
        }

        $catalog = new CatalogQueryService($request->user());
        $listing = $this->buildCatalogListing($request, $category, $catalog);

        return view('manufacturer.catalog.index', array_merge($listing, [
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
        ]));
    }

    public function catalogProducts(Request $request): Response
    {
        $catalogRole = app(CurrentRoleService::class)->get($request->user());
        $category = $this->resolveCategoryFromRequest($catalogRole, $request->get('category'));
        $catalog = new CatalogQueryService($request->user());
        $listing = $this->buildCatalogListing($request, $category, $catalog);

        return response()->view('catalog._products', array_merge($listing, [
            'selectedCategory' => $category,
            'selectedCategoryId' => $category?->id,
            'catalogIndexRoute' => 'manufacturer.catalog.index',
            'catalogShowRoute' => 'manufacturer.catalog.show',
            'showNomenclatureLink' => true,
        ]))->header('Cache-Control', 'no-store');
    }

    public function catalogShow(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        if (! $product->isVisibleInCatalog()) {
            abort(404);
        }

        $product->load([
            'manufacturerProfile.regions',
            'category.parent',
            'additionalCategories',
            'images',
            'unitType',
            'attributeValues.attribute',
            'stocks.warehouse.region',
            'documents',
            'analogs.images',
            'analogs.category',
            'analogs.attributeValues.attribute',
        ]);

        $catalog = new \App\Services\Catalog\CatalogQueryService($request->user());
        $card = new \App\Services\Catalog\ProductCatalogCardService($request->user(), $catalog);
        $cardData = $card->build($product);

        return view('manufacturer.catalog.show', array_merge($cardData, [
            'product' => $product,
            'backUrl' => $product->category?->slug
                ? route('manufacturer.catalog.index', $product->category->slug)
                : route('manufacturer.catalog.index'),
            'analogShowRoute' => 'manufacturer.catalog.show',
            'showActions' => true,
            'liveUrl' => route('manufacturer.catalog.product.live', $product),
        ]));
    }

    /** Разрешает категорию по slug или id из query (для AJAX/форм). */
    private function resolveCategoryFromRequest(?Role $catalogRole, ?string $value): ?ProductCategory
    {
        if ($value === null || $value === '') {
            return null;
        }
        $category = is_numeric($value)
            ? ProductCategory::active()->find((int) $value)
            : ProductCategory::active()->where('slug', $value)->first();

        if ($category && ! $category->isShownInCatalogForRole($catalogRole)) {
            return null;
        }

        return $category;
    }

    private function applyIndexSorting($query, Request $request): void
    {
        $sortField = $request->get('sort', 'updated_at');
        $allowedSorts = ['sku', 'name', 'base_price', 'status', 'updated_at', 'category', 'stock'];
        if (! in_array($sortField, $allowedSorts, true)) {
            $sortField = 'updated_at';
        }
        $sortDir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        match ($sortField) {
            'category' => $query->orderBy(
                ProductCategory::select('name')
                    ->whereColumn('product_categories.id', 'products.category_id')
                    ->limit(1),
                $sortDir
            ),
            'stock' => $query->orderByRaw(
                '(SELECT COALESCE(SUM(quantity - reserved), 0) FROM product_stocks WHERE product_id = products.id) '.$sortDir
            ),
            default => $query->orderBy($sortField, $sortDir),
        };
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        if ($product->manufacturer_profile_id !== $request->user()->manufacturerProfile?->id) {
            abort(403);
        }
    }
}

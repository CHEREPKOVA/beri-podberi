<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\Region;
use App\Models\UnitType;
use App\Services\Catalog\ProductCardContentService;
use App\Services\Catalog\ProductQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CatalogProductController extends Controller
{
    public function __construct(
        private readonly ProductCardContentService $cardContent,
        private readonly ProductQualityService $quality,
    ) {}

    public function index(Request $request): View
    {
        $query = Product::query()->with(['manufacturerProfile', 'category']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $categoryIds = collect($request->input('category_ids', []))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        $products = $query->latest('updated_at')->paginate(25)->withQueryString();
        $categories = ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $categoryTree = ProductCategory::adminTree();

        return view('admin.catalog.products.index', compact('products', 'categories', 'categoryTree'));
    }

    public function edit(Request $request, Product $product): View
    {
        $product->load([
            'manufacturerProfile',
            'category',
            'additionalCategories',
            'unitType',
            'images',
            'attributeValues.attribute',
            'documents',
            'availableRegions',
        ]);

        $categoryTree = ProductCategory::assignableTree();
        $categories = ProductCategory::query()
            ->active()
            ->assignableForProducts()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $unitTypes = UnitType::active()->orderBy('name')->get();
        $regions = Region::query()->active()->orderBy('name')->get();
        $attributes = ProductAttribute::active()
            ->forCategory($product->category_id)
            ->orderBy('sort_order')
            ->get();
        $qualityIssues = $this->quality->catalogCardIssues($product);

        return view('admin.catalog.products.edit', [
            'product' => $product,
            'categories' => $categories,
            'categoryTree' => $categoryTree,
            'unitTypes' => $unitTypes,
            'regions' => $regions,
            'attributes' => $attributes,
            'tab' => $request->get('tab', 'basic'),
            'qualityIssues' => $qualityIssues,
            'mediaRoutes' => [
                'image_delete' => 'admin.catalog.products.image.delete',
                'image_primary' => 'admin.catalog.products.image.primary',
                'document_delete' => 'admin.catalog.products.document.delete',
            ],
        ]);
    }

    public function show(Request $request, Product $product): View
    {
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

        return view('admin.catalog.products.show', array_merge($cardData, [
            'product' => $product,
            'backUrl' => route('admin.catalog.products.index'),
            'backLabel' => 'Назад к списку товаров',
            'analogShowRoute' => 'admin.catalog.products.show',
            'showActions' => true,
            'qualityIssues' => $this->quality->catalogCardIssues($product),
            'liveUrl' => route('admin.catalog.products.live', $product),
        ]));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->cardContent->validate($request);

        DB::beginTransaction();
        try {
            $this->cardContent->syncFromRequest($request, $product, $validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('admin.catalog.products.edit', ['product' => $product, 'tab' => $request->get('tab', 'basic')])
            ->with('success', 'Карточка товара обновлена.');
    }

    public function deleteImage(ProductImage $image): RedirectResponse
    {
        $product = $image->product;
        Storage::disk('public')->delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $product->images()->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Изображение удалено.');
    }

    public function setPrimaryImage(ProductImage $image): RedirectResponse
    {
        $product = $image->product;
        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', 'Основное изображение установлено.');
    }

    public function deleteDocument(ProductDocument $document): RedirectResponse
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Документ удалён.');
    }
}

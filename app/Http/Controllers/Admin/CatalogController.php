<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        $stats = [
            'categories_total' => ProductCategory::query()->count(),
            'categories_active' => ProductCategory::query()->where('is_active', true)->count(),
            'products_total' => Product::query()->count(),
            'products_without_category' => Product::query()->whereNull('category_id')->count(),
            'products_without_attributes' => Product::query()->doesntHave('attributeValues')->count(),
            'products_without_images' => Product::query()->doesntHave('images')->count(),
        ];

        return view('admin.catalog.index', compact('stats'));
    }

    public function quality(): View
    {
        $withoutCategory = Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->whereNull('category_id')
            ->latest('updated_at')
            ->paginate(20, ['*'], 'without_category');

        $withoutAttributes = Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->doesntHave('attributeValues')
            ->latest('updated_at')
            ->paginate(20, ['*'], 'without_attributes');

        $withoutImages = Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->doesntHave('images')
            ->latest('updated_at')
            ->paginate(20, ['*'], 'without_images');

        return view('admin.catalog.quality', compact('withoutCategory', 'withoutAttributes', 'withoutImages'));
    }
}

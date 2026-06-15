<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Catalog\ProductQualityService;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(
        private readonly ProductQualityService $quality,
    ) {}

    public function index(): View
    {
        $stats = [
            'categories_total' => ProductCategory::query()->count(),
            'categories_active' => ProductCategory::query()->where('is_active', true)->count(),
            'products_total' => Product::query()->count(),
            'products_without_category' => $this->quality->productsWithoutCategoryQuery()->count(),
            'products_without_attributes' => $this->quality->productsWithoutAnyAttributesQuery()->count(),
            'products_missing_required_attributes' => $this->quality->productsWithMissingRequiredAttributesQuery()->count(),
            'products_without_images' => $this->quality->productsWithoutImagesQuery()->count(),
        ];

        return view('admin.catalog.index', compact('stats'));
    }

    public function quality(): View
    {
        $withoutCategory = $this->quality->productsWithoutCategoryQuery()
            ->latest('updated_at')
            ->paginate(20, ['*'], 'without_category');

        $withoutAttributes = $this->quality->productsWithoutAnyAttributesQuery()
            ->latest('updated_at')
            ->paginate(20, ['*'], 'without_attributes');

        $missingRequiredAttributes = $this->quality->productsWithMissingRequiredAttributesQuery()
            ->latest('updated_at')
            ->paginate(20, ['*'], 'missing_required');

        $withoutImages = $this->quality->productsWithoutImagesQuery()
            ->latest('updated_at')
            ->paginate(20, ['*'], 'without_images');

        $duplicateGroups = $this->quality->duplicateGroups(15);

        return view('admin.catalog.quality', compact(
            'withoutCategory',
            'withoutAttributes',
            'missingRequiredAttributes',
            'withoutImages',
            'duplicateGroups',
        ));
    }
}

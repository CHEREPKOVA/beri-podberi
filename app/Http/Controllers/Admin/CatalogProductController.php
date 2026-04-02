<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with(['manufacturerProfile', 'category']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->latest('updated_at')->paginate(25)->withQueryString();
        $categories = ProductCategory::query()->active()->orderBy('name')->get();

        return view('admin.catalog.products.index', compact('products', 'categories'));
    }

    public function edit(Product $product): View
    {
        $product->load(['manufacturerProfile', 'category', 'additionalCategories']);
        $categories = ProductCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.catalog.products.edit', compact('product', 'categories'));
    }

    public function show(Product $product): View
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
        ]);

        $supplierRows = collect([[
            'name' => $product->manufacturerProfile?->short_name ?: ($product->manufacturerProfile?->full_name ?? 'Производитель'),
            'price' => $product->base_price,
            'stock' => $product->available_stock,
            'conditions' => $product->transport_conditions ?: 'По стандартным условиям производителя',
            'regions' => $product->manufacturerProfile?->regions?->pluck('name')->implode(', ') ?: 'Все регионы',
        ]]);

        return view('admin.catalog.products.show', compact('product', 'supplierRows'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'additional_category_ids' => ['nullable', 'array'],
            'additional_category_ids.*' => ['exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,hidden,draft'],
            'show_in_catalog' => ['sometimes', 'boolean'],
        ]);

        $product->update([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'show_in_catalog' => $request->boolean('show_in_catalog'),
        ]);

        $additionalIds = $validated['additional_category_ids'] ?? [];
        $additionalIds = array_values(array_filter($additionalIds, fn ($id) => (int) $id !== (int) $product->category_id));
        $product->additionalCategories()->sync($additionalIds);

        return redirect()->route('admin.catalog.products.edit', $product)->with('success', 'Карточка товара обновлена.');
    }
}

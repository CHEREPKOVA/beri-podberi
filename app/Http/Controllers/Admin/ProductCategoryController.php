<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = ProductCategory::query()->with('parent');

        if ($request->filled('search')) {
            $s = (string) $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.catalog.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $parents = ProductCategory::query()->orderBy('name')->get();

        return view('admin.catalog.categories.create', compact('parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);
        ProductCategory::query()->create($validated);

        return redirect()->route('admin.catalog.categories.index')->with('success', 'Категория создана.');
    }

    public function edit(ProductCategory $category): View
    {
        $parents = ProductCategory::query()->where('id', '!=', $category->id)->orderBy('name')->get();

        return view('admin.catalog.categories.edit', compact('category', 'parents'));
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $this->validateData($request, $category);
        $category->update($validated);

        return redirect()->route('admin.catalog.categories.index')->with('success', 'Категория обновлена.');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            return redirect()->route('admin.catalog.categories.index')
                ->with('error', 'Категория используется в дереве или товарах. Снимите активность вместо удаления.');
        }

        $category->delete();

        return redirect()->route('admin.catalog.categories.index')->with('success', 'Категория удалена.');
    }

    private function validateData(Request $request, ?ProductCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('product_categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');

        if ($category && isset($validated['parent_id']) && (int) $validated['parent_id'] === (int) $category->id) {
            $validated['parent_id'] = null;
        }

        return $validated;
    }
}

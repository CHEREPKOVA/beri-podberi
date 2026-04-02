<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductAttributeController extends Controller
{
    public function index(Request $request): View
    {
        $query = ProductAttribute::query()->with('productCategory');

        if ($request->filled('search')) {
            $s = (string) $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $attributes = $query->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.catalog.attributes.index', compact('attributes'));
    }

    public function create(): View
    {
        return view('admin.catalog.attributes.create', [
            'categories' => ProductCategory::query()->active()->orderBy('name')->get(),
            'types' => ProductAttribute::typeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);
        ProductAttribute::query()->create($validated);

        return redirect()->route('admin.catalog.attributes.index')->with('success', 'Свойство добавлено.');
    }

    public function edit(ProductAttribute $attribute): View
    {
        return view('admin.catalog.attributes.edit', [
            'attribute' => $attribute,
            'categories' => ProductCategory::query()->active()->orderBy('name')->get(),
            'types' => ProductAttribute::typeLabels(),
        ]);
    }

    public function update(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $validated = $this->validateData($request, $attribute);
        $attribute->update($validated);

        return redirect()->route('admin.catalog.attributes.index')->with('success', 'Свойство обновлено.');
    }

    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        if ($attribute->values()->exists()) {
            return redirect()->route('admin.catalog.attributes.index')
                ->with('error', 'Нельзя удалить свойство: по нему уже заполнены значения у товаров.');
        }

        $attribute->delete();

        return redirect()->route('admin.catalog.attributes.index')->with('success', 'Свойство удалено.');
    }

    private function validateData(Request $request, ?ProductAttribute $attribute = null): array
    {
        $validated = $request->validate([
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_attributes', 'slug')
                    ->where(fn ($q) => $q->where('product_category_id', $request->input('product_category_id')))
                    ->ignore($attribute?->id),
            ],
            'type' => ['required', Rule::in(array_keys(ProductAttribute::typeLabels()))],
            'options_raw' => ['nullable', 'string'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $options = null;
        if (($validated['type'] ?? null) === ProductAttribute::TYPE_SELECT && ! empty($validated['options_raw'])) {
            $items = preg_split('/\r\n|\r|\n/', trim($validated['options_raw']));
            $options = array_values(array_filter(array_map('trim', $items)));
        }

        return [
            'product_category_id' => $validated['product_category_id'] ?? null,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'] ?: $validated['name']),
            'type' => $validated['type'],
            'options' => $options,
            'is_filterable' => $request->boolean('is_filterable'),
            'is_required' => $request->boolean('is_required'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}

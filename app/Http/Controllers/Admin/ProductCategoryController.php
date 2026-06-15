<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = ProductCategory::query()->with('parent')->withCount('catalogRoles');

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
        $roles = Role::query()->orderBy('sort_order')->orderBy('name')->get();
        $excludableAttributes = collect();

        return view('admin.catalog.categories.create', array_merge(
            $this->categoryPickerContext(),
            compact('roles', 'excludableAttributes')
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $roleIds = $this->validatedRoleSyncIds($request);

        unset($payload['catalog_role_ids'], $payload['excluded_attribute_ids']);

        DB::transaction(function () use ($payload, $roleIds, $request): void {
            $category = ProductCategory::query()->create($payload);
            $category->catalogRoles()->sync($roleIds);
            $category->excludedAttributes()->sync($this->validatedExcludedAttributeIds($category, $request));
        });

        return redirect()->route('admin.catalog.categories.index')->with('success', 'Категория создана.');
    }

    public function edit(ProductCategory $category): View
    {
        $roles = Role::query()->orderBy('sort_order')->orderBy('name')->get();
        $excludableAttributes = ProductAttribute::query()
            ->whereIn('id', $category->excludableInheritedAttributeIds())
            ->orderBy('name')
            ->get();
        $category->load(['catalogRoles', 'excludedAttributes']);

        return view('admin.catalog.categories.edit', array_merge(
            $this->categoryPickerContext($category),
            compact('category', 'roles', 'excludableAttributes')
        ));
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $roleIds = $this->validatedRoleSyncIds($request);
        unset($payload['catalog_role_ids'], $payload['excluded_attribute_ids']);

        DB::transaction(function () use ($category, $payload, $roleIds, $request) {
            $category->update($payload);
            $category->catalogRoles()->sync($roleIds);
            $category->excludedAttributes()->sync($this->validatedExcludedAttributeIds($category, $request));
        });

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

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_categories', 'slug')->ignore(optional($request->route('category'))?->getKey()),
            ],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'shown_in_customer_catalog' => ['sometimes', 'boolean'],
            'restrict_catalog_by_roles' => ['sometimes', 'boolean'],
            'accepts_products' => ['sometimes', 'boolean'],
            'catalog_role_ids' => ['nullable', 'array'],
            'catalog_role_ids.*' => ['integer', 'exists:roles,id'],
            'excluded_attribute_ids' => ['nullable', 'array'],
            'excluded_attribute_ids.*' => ['integer', 'exists:product_attributes,id'],
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['shown_in_customer_catalog'] = $request->boolean('shown_in_customer_catalog');
        $validated['restrict_catalog_by_roles'] = $request->boolean('restrict_catalog_by_roles');
        $validated['accepts_products'] = $request->boolean('accepts_products');

        $category = $request->route('category');

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        if ($category && $parentId === (int) $category->id) {
            $validated['parent_id'] = null;
        }

        return $validated;
    }

    /**
     * @return array<int>
     */
    private function validatedRoleSyncIds(Request $request): array
    {
        if (! $request->boolean('restrict_catalog_by_roles')) {
            return [];
        }

        /** @var list<int|string> $ids */
        $ids = $request->input('catalog_role_ids', []);

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * @return array{categoryTree: \Illuminate\Support\Collection, categories: \Illuminate\Support\Collection<int, ProductCategory>}
     */
    private function categoryPickerContext(?ProductCategory $excludeCategory = null): array
    {
        $excludeIds = $this->excludedParentIds($excludeCategory);

        $categories = ProductCategory::active()
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'categoryTree' => ProductCategory::buildTree($categories),
            'categories' => $categories,
        ];
    }

    /**
     * @return list<int>
     */
    private function excludedParentIds(?ProductCategory $category): array
    {
        if ($category === null) {
            return [];
        }

        $exclude = [$category->id];
        $all = ProductCategory::query()->get(['id', 'parent_id']);

        do {
            $added = false;
            foreach ($all as $row) {
                if (in_array($row->parent_id, $exclude, true) && ! in_array($row->id, $exclude, true)) {
                    $exclude[] = $row->id;
                    $added = true;
                }
            }
        } while ($added);

        return $exclude;
    }

    /**
     * @return array<int>
     */
    private function validatedExcludedAttributeIds(ProductCategory $category, Request $request): array
    {
        /** @var list<int|string> $ids */
        $ids = $request->input('excluded_attribute_ids', []);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_values(array_intersect($ids, $category->excludableInheritedAttributeIds()));
    }
}

<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductCardContentService
{
    public function validate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'exists:product_categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $cat = ProductCategory::query()->find((int) $value);
                    if ($cat && ! $cat->accepts_products) {
                        $fail('Категория только для подкатегорий: выберите категорию, куда допускается привязка товаров.');
                    }
                },
            ],
            'additional_category_ids' => ['nullable', 'array'],
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
            'unit_type_id' => ['nullable', 'exists:unit_types,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1'],
            'manufacturer_sku' => ['nullable', 'string', 'max:100'],
            'distributor_sku' => ['nullable', 'string', 'max:100'],
            'ean' => ['nullable', 'string', 'max:20'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date'],
            'storage_conditions' => ['nullable', 'string', 'max:500'],
            'transport_conditions' => ['nullable', 'string', 'max:500'],
            'instruction_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_HIDDEN, Product::STATUS_DRAFT])],
            'show_in_catalog' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'mark_is_new' => ['sometimes', 'boolean'],
            'mark_on_sale' => ['sometimes', 'boolean'],
            'mark_discontinued' => ['sometimes', 'boolean'],
        ]);
    }

    public function syncFromRequest(Request $request, Product $product, array $validated): void
    {
        $product->update([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'unit_type_id' => $validated['unit_type_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
            'min_order_quantity' => $validated['min_order_quantity'] ?? null,
            'manufacturer_sku' => $validated['manufacturer_sku'] ?? null,
            'distributor_sku' => $validated['distributor_sku'] ?? null,
            'ean' => $validated['ean'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'storage_conditions' => $validated['storage_conditions'] ?? null,
            'transport_conditions' => $validated['transport_conditions'] ?? null,
            'instruction_url' => $validated['instruction_url'] ?? null,
            'status' => $validated['status'],
            'show_in_catalog' => $request->boolean('show_in_catalog'),
            'published_at' => $validated['published_at'] ?? $product->published_at,
            'mark_is_new' => $request->boolean('mark_is_new'),
            'mark_on_sale' => $request->boolean('mark_on_sale'),
            'mark_discontinued' => $request->boolean('mark_discontinued'),
        ]);

        $this->syncAdditionalCategories($request, $product);
        $this->syncImages($request, $product);
        $this->syncAttributes($request, $product);
        $this->syncRegions($request, $product);
        $this->syncDocuments($request, $product);
    }

    public function syncImages(Request $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $hasImages = $product->images()->exists();

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store('products/images', 'public');

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'is_primary' => ! $hasImages && $index === 0,
                'sort_order' => ($product->images()->max('sort_order') ?? 0) + 1,
            ]);

            $hasImages = true;
        }
    }

    public function syncAttributes(Request $request, Product $product): void
    {
        if ($request->has('attributes')) {
            $this->syncSystemAttributes($request, $product);
        }

        $this->syncCustomAttributes($request, $product);
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

    private function syncCustomAttributes(Request $request, Product $product): void
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

    public function syncRegions(Request $request, Product $product): void
    {
        if ($request->has('available_regions')) {
            $product->availableRegions()->sync($request->available_regions);
        } else {
            $product->availableRegions()->detach();
        }
    }

    public function syncAdditionalCategories(Request $request, Product $product): void
    {
        $ids = $request->input('additional_category_ids', []);
        $mainId = $product->category_id;
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) $mainId));
        $product->additionalCategories()->sync($ids);
    }

    public function syncDocuments(Request $request, Product $product): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        foreach ($request->file('documents') as $index => $doc) {
            $path = $doc->store('products/documents', 'public');

            ProductDocument::create([
                'product_id' => $product->id,
                'name' => $request->input('document_names.'.$index, $doc->getClientOriginalName()),
                'type' => $request->input('document_types.'.$index, 'other'),
                'file_path' => $path,
                'original_name' => $doc->getClientOriginalName(),
                'mime_type' => $doc->getMimeType(),
                'file_size' => $doc->getSize(),
            ]);
        }
    }
}

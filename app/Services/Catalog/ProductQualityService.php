<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductQualityService
{
    public function productsWithoutCategoryQuery(): Builder
    {
        return Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->whereNull('category_id');
    }

    public function productsWithoutAnyAttributesQuery(): Builder
    {
        return Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->doesntHave('attributeValues');
    }

    public function productsWithoutImagesQuery(): Builder
    {
        return Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->doesntHave('images');
    }

    /** Товары с незаполненными обязательными характеристиками категории. */
    public function productsWithMissingRequiredAttributesQuery(): Builder
    {
        return Product::query()
            ->with(['manufacturerProfile', 'category'])
            ->whereNotNull('category_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_attributes')
                    ->where('product_attributes.is_active', true)
                    ->where('product_attributes.is_required', true)
                    ->whereNull('product_attributes.product_id')
                    ->where(function ($categoryMatch) {
                        $categoryMatch
                            ->whereColumn('product_attributes.product_category_id', 'products.category_id')
                            ->orWhereExists(function ($pivot) {
                                $pivot->select(DB::raw(1))
                                    ->from('product_attribute_category as pac')
                                    ->whereColumn('pac.product_attribute_id', 'product_attributes.id')
                                    ->whereColumn('pac.product_category_id', 'products.category_id');
                            });
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('product_attribute_values')
                            ->whereColumn('product_attribute_values.product_id', 'products.id')
                            ->whereColumn('product_attribute_values.product_attribute_id', 'product_attributes.id')
                            ->whereRaw('TRIM(product_attribute_values.value) != ?', ['']);
                    });
            });
    }

    /**
     * Группы возможных дубликатов по EAN, штрихкоду или совпадению названия у одного производителя.
     *
     * @return Collection<int, Collection<int, Product>>
     */
    public function duplicateGroups(int $limit = 20): Collection
    {
        $groups = collect();

        foreach (['ean', 'barcode'] as $field) {
            $duplicateValues = Product::query()
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->select($field)
                ->groupBy($field)
                ->havingRaw('COUNT(*) > 1')
                ->limit($limit)
                ->pluck($field);

            foreach ($duplicateValues as $value) {
                $products = Product::query()
                    ->with(['manufacturerProfile', 'category'])
                    ->where($field, $value)
                    ->orderBy('name')
                    ->get();

                if ($products->count() > 1) {
                    $groups->push($products);
                }
            }
        }

        $nameDuplicates = Product::query()
            ->select('manufacturer_profile_id', 'name')
            ->groupBy('manufacturer_profile_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get();

        foreach ($nameDuplicates as $row) {
            $products = Product::query()
                ->with(['manufacturerProfile', 'category'])
                ->where('manufacturer_profile_id', $row->manufacturer_profile_id)
                ->where('name', $row->name)
                ->orderBy('sku')
                ->get();

            if ($products->count() > 1) {
                $groups->push($products);
            }
        }

        return $groups->unique(fn (Collection $group) => $group->pluck('id')->sort()->implode(','))->take($limit)->values();
    }

    public function missingRequiredAttributeNames(Product $product): array
    {
        if (! $product->category_id) {
            return [];
        }

        $required = ProductAttribute::query()
            ->active()
            ->forCategory($product->category_id)
            ->where('is_required', true)
            ->orderBy('sort_order')
            ->get();

        if ($required->isEmpty()) {
            return [];
        }

        $product->loadMissing('attributeValues');

        $filledIds = $product->attributeValues
            ->filter(fn ($value) => trim((string) $value->value) !== '')
            ->pluck('product_attribute_id')
            ->all();

        return $required
            ->whereNotIn('id', $filledIds)
            ->pluck('name')
            ->all();
    }

    public function catalogCardIssues(Product $product): array
    {
        $issues = [];

        if (! $product->category_id) {
            $issues[] = 'Не назначена категория';
        }

        $missingAttributes = $this->missingRequiredAttributeNames($product);
        if ($missingAttributes !== []) {
            $issues[] = 'Не заполнены обязательные характеристики: '.implode(', ', $missingAttributes);
        }

        if ($product->images()->count() === 0) {
            $issues[] = 'Нет изображений';
        }

        if (trim((string) $product->description) === '') {
            $issues[] = 'Пустое описание';
        }

        return $issues;
    }
}

<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<ProductCategoryFactory> */
class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'shown_in_customer_catalog',
        'restrict_catalog_by_roles',
        'accepts_products',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'shown_in_customer_catalog' => 'boolean',
            'restrict_catalog_by_roles' => 'boolean',
            'accepts_products' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class, 'product_category_id');
    }

    /** Доступ в пользовательском каталоге ограничен этими ролями (если restrict_catalog_by_roles = true). */
    public function catalogRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'product_category_role', 'product_category_id', 'role_id')
            ->withTimestamps();
    }

    /** Унаследованные свойства, отключённые на этой ветке (не показываются в карточке и фильтрах). */
    public function excludedAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttribute::class,
            'product_category_excluded_attributes',
            'product_category_id',
            'product_attribute_id'
        )->withTimestamps();
    }

    /** Товары, у которых эта категория указана как дополнительная */
    public function productsAsAdditional(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_product', 'product_category_id', 'product_id')
            ->withTimestamps();
    }

    /** ID категории и всех предков (для подтягивания атрибутов) */
    public function ancestorIds(): array
    {
        $ids = [];
        $parent = $this->parent;
        while ($parent) {
            $ids[] = $parent->id;
            $parent = $parent->parent;
        }

        return $ids;
    }

    /** Ид свойств предков и глобальных, которые можно отключить для этой подкатегории */
    public function excludableInheritedAttributeIds(): array
    {
        $ancestorIds = $this->ancestorIds();

        return ProductAttribute::query()
            ->where('is_active', true)
            ->where(function ($q) use ($ancestorIds) {
                $q->whereNull('product_category_id');
                foreach ($ancestorIds as $aid) {
                    $q->orWhere('product_category_id', $aid);
                }
            })
            ->pluck('id')
            ->all();
    }

    /** ID этой категории и всех потомков (для фильтрации товаров по категории с подкатегориями) */
    public function getDescendantIdsAttribute(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendant_ids);
        }

        return $ids;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAssignableForProducts($query)
    {
        return $query->where('accepts_products', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /** Маршрутизация по slug (ЧПУ) */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Видимость узла дерева пользовательского каталога при текущей роли сессии.
     */
    public function isShownInCatalogForRole(?Role $catalogRole): bool
    {
        if (! $this->is_active || ! $this->shown_in_customer_catalog) {
            return false;
        }
        if (! $this->restrict_catalog_by_roles) {
            return true;
        }

        return $catalogRole instanceof Role
            && $this->catalogRoles()->where('roles.id', $catalogRole->id)->exists();
    }

    /** Дерево категорий (корни с вложенными children). Фильтр по роли и признакам пользовательского каталога (ТЗ). */
    public static function getTree(bool $hideEmpty = false, ?int $manufacturerProfileId = null, ?Role $catalogRole = null): \Illuminate\Support\Collection
    {
        $query = self::active()
            ->where('shown_in_customer_catalog', true)
            ->where(function ($outer) use ($catalogRole) {
                $outer->where('restrict_catalog_by_roles', false);
                if ($catalogRole instanceof Role) {
                    $outer->orWhere(function ($nested) use ($catalogRole) {
                        $nested->where('restrict_catalog_by_roles', true)
                            ->whereHas('catalogRoles', fn ($r) => $r->where('roles.id', $catalogRole->id));
                    });
                }
            })
            ->orderBy('sort_order');

        $all = $query->get();
        $keyed = $all->keyBy('id');
        foreach ($all as $cat) {
            $cat->setRelation('children', collect());
        }
        $roots = collect();
        foreach ($all as $cat) {
            if ($cat->parent_id === null) {
                $roots->push($cat);
            } else {
                $parent = $keyed->get($cat->parent_id);
                if ($parent) {
                    $parent->children->push($cat);
                } else {
                    $roots->push($cat);
                }
            }
        }
        if ($hideEmpty && $manufacturerProfileId !== null) {
            $roots = self::filterTreeByProducts($roots, $manufacturerProfileId);
        }

        return $roots;
    }

    /** Оставляет только ветки, в которых есть товары производителя (в категории или подкатегориях). */
    protected static function filterTreeByProducts(\Illuminate\Support\Collection $nodes, int $manufacturerProfileId): \Illuminate\Support\Collection
    {
        return $nodes->map(function ($node) use ($manufacturerProfileId) {
            $childIds = $node->descendant_ids;
            $hasProducts = Product::forManufacturer($manufacturerProfileId)
                ->whereNotNull('category_id')
                ->whereIn('category_id', $childIds)
                ->exists();
            if (! $hasProducts) {
                return null;
            }
            $filteredChildren = self::filterTreeByProducts($node->children, $manufacturerProfileId);
            $node->setRelation('children', $filteredChildren->filter());

            return $node;
        })->filter();
    }

    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' / ', $path);
    }
}

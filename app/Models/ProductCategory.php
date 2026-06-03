<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    /** Свойства, явно привязанные к категории (в т.ч. через несколько категорий). */
    public function catalogAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttribute::class,
            'product_attribute_category',
            'product_category_id',
            'product_attribute_id'
        )->withTimestamps();
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
            ->whereNull('product_id')
            ->where(function ($q) use ($ancestorIds) {
                $q->where(function ($global) {
                    $global->whereDoesntHave('categories')->whereNull('product_category_id');
                });
                foreach ($ancestorIds as $aid) {
                    $q->orWhereHas('categories', fn ($cq) => $cq->where('product_categories.id', $aid))
                        ->orWhere('product_category_id', $aid);
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

    /** Slug предков (для раскрытия ветки в дереве каталога). */
    public function ancestorSlugs(): array
    {
        $slugs = [];
        $parent = $this->parent;
        while ($parent) {
            $slugs[] = $parent->slug;
            $parent = $parent->parent;
        }

        return $slugs;
    }

    /** Подгружает цепочку parent для ancestorSlugs(). */
    public function loadAncestors(): self
    {
        if ($this->parent_id) {
            $this->loadMissing('parent');
            $this->parent?->loadAncestors();
        }

        return $this;
    }

    /**
     * Начальное раскрытие узлов дерева каталога: все родители или путь к выбранной категории.
     *
     * @return array<string, true>
     */
    public static function initialOpenSlugsForCatalogTree(Collection $roots, ?self $selected): array
    {
        $open = [];
        if ($selected) {
            foreach ($selected->ancestorSlugs() as $slug) {
                $open[$slug] = true;
            }
            $nodeInTree = self::findInCatalogTree($roots, $selected->slug);
            if ($nodeInTree && $nodeInTree->children->isNotEmpty()) {
                $open[$selected->slug] = true;
            }
        } else {
            self::collectOpenSlugsForAllParents($roots, $open);
        }

        return $open;
    }

    public static function findInCatalogTree(Collection $nodes, string $slug): ?self
    {
        foreach ($nodes as $node) {
            if ($node->slug === $slug) {
                return $node;
            }
            $found = self::findInCatalogTree($node->children, $slug);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /** @param  array<string, true>  $open */
    protected static function collectOpenSlugsForAllParents(Collection $nodes, array &$open): void
    {
        foreach ($nodes as $node) {
            if ($node->children->isNotEmpty()) {
                $open[$node->slug] = true;
                self::collectOpenSlugsForAllParents($node->children, $open);
            }
        }
    }

    /** Дерево категорий (корни с вложенными children). Фильтр по роли и признакам пользовательского каталога (ТЗ). */
    public static function getTree(bool $hideEmpty = false, ?int $manufacturerProfileId = null, ?Role $catalogRole = null): Collection
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
        if ($hideEmpty) {
            if ($manufacturerProfileId !== null) {
                $roots = self::filterTreeByProductVisibility(
                    $roots,
                    fn (array $categoryIds): bool => Product::forManufacturer($manufacturerProfileId)
                        ->visibleInCatalog()
                        ->inAnyCategoryIds($categoryIds)
                        ->exists()
                );
            }
        }

        return $roots;
    }

    /**
     * Оставляет ветки с товарами по произвольному предикату (регион, партнёрство и т.д.).
     *
     * @param  callable(array<int, int>): bool  $hasProductsInCategories
     */
    public static function filterTreeByProductVisibility(
        Collection $nodes,
        callable $hasProductsInCategories,
    ): Collection {
        return $nodes->map(function ($node) use ($hasProductsInCategories) {
            $children = self::filterTreeByProductVisibility($node->children, $hasProductsInCategories)
                ->sortBy('sort_order')
                ->values();

            $hasProducts = $hasProductsInCategories($node->descendant_ids);

            if (! $hasProducts && $children->isEmpty()) {
                return null;
            }

            $node->setRelation('children', $children);

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

    /** Дерево категорий, в которые можно назначать товары (для форм и фильтров). */
    public static function assignableTree(): Collection
    {
        $categories = self::active()
            ->assignableForProducts()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return self::buildTree($categories);
    }

    /** Дерево всех активных категорий (формы админки). */
    public static function adminTree(): Collection
    {
        return self::buildTree(
            self::active()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    /**
     * Плоский список для селекта с отступами по уровню вложенности.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function selectOptionsFromTree(Collection $roots, int $depth = 0): array
    {
        $options = [];
        foreach ($roots as $node) {
            $indent = $depth > 0 ? str_repeat(' ', $depth).'↳ ' : '';
            $options[] = [
                'value' => $node->id,
                'label' => $indent.$node->name,
            ];
            if ($node->children->isNotEmpty()) {
                $options = array_merge($options, self::selectOptionsFromTree($node->children, $depth + 1));
            }
        }

        return $options;
    }

    /** @param  Collection<int, self>  $categories */
    public static function buildTree(Collection $categories): Collection
    {
        $keyed = $categories->keyBy('id');
        foreach ($categories as $category) {
            $category->setRelation('children', collect());
        }

        $roots = collect();
        foreach ($categories as $category) {
            if ($category->parent_id === null || ! $keyed->has($category->parent_id)) {
                $roots->push($category);
            } else {
                $keyed->get($category->parent_id)->children->push($category);
            }
        }

        return self::sortTreeNodes($roots);
    }

    /** @param  Collection<int, self>  $nodes */
    protected static function sortTreeNodes(Collection $nodes): Collection
    {
        return $nodes->sortBy('sort_order')->values()->map(function (self $node) {
            if ($node->children->isNotEmpty()) {
                $node->setRelation('children', self::sortTreeNodes($node->children));
            }

            return $node;
        });
    }
}

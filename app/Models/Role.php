<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SLUG_ADMIN = 'admin';
    public const SLUG_MANAGER = 'manager';
    public const SLUG_ANALYST = 'analyst';
    public const SLUG_MANUFACTURER = 'manufacturer';
    public const SLUG_DISTRIBUTOR = 'distributor';
    public const SLUG_END_COMPANY = 'end_company';
    public const SLUG_COMPANY_EMPLOYEE = 'company_employee';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')->withTimestamps();
    }

    /** Категории каталога, видимые только выбранным ролям при restrict_catalog_by_roles. */
    public function catalogCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_role', 'role_id', 'product_category_id')
            ->withTimestamps();
    }

    public function label(): string
    {
        return $this->name ?: config("roles.labels.{$this->slug}", $this->slug);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isInUse(): bool
    {
        return $this->users()->exists();
    }

    /**
     * @return array<int, string>
     */
    public static function protectedSlugs(): array
    {
        return [
            self::SLUG_ADMIN,
            self::SLUG_MANAGER,
            self::SLUG_ANALYST,
            self::SLUG_MANUFACTURER,
            self::SLUG_DISTRIBUTOR,
            self::SLUG_END_COMPANY,
            self::SLUG_COMPANY_EMPLOYEE,
        ];
    }

    /**
     * Подпись для модального окна выбора роли: «Роль – компания «Название»» или просто роль.
     */
    public function labelWithCompany(?string $companyName): string
    {
        if ($companyName !== null && $companyName !== '') {
            $short = config("roles.short_labels.{$this->slug}");
            $roleTitle = $short ?? $this->label();
            return $roleTitle . ' – компания «' . e($companyName) . '»';
        }
        return $this->label();
    }

    public function isAdminPanel(): bool
    {
        return in_array($this->slug, config('roles.admin_panel_roles', []), true);
    }

    public function isSwitchableProfile(): bool
    {
        return in_array($this->slug, config('roles.switchable_profiles', []), true);
    }

    /**
     * Корпоративная роль, в рамках которой можно добавлять внутренних сотрудников
     * (производитель, дистрибьютор, конечная компания).
     */
    public function canHaveEmployees(): bool
    {
        return in_array($this->slug, config('roles.corporate_roles_with_employees', []), true);
    }

    /**
     * Слаги ролей, которые могут приглашать сотрудников.
     *
     * @return array<int, string>
     */
    public static function corporateSlugsWithEmployees(): array
    {
        return CompanyType::activeSlugs();
    }

    /**
     * Найти роль по слагу.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}

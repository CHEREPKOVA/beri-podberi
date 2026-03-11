<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SLUG_ADMIN = 'admin';
    public const SLUG_MANAGER = 'manager';
    public const SLUG_MANUFACTURER = 'manufacturer';
    public const SLUG_DISTRIBUTOR = 'distributor';
    public const SLUG_END_COMPANY = 'end_company';
    public const SLUG_COMPANY_EMPLOYEE = 'company_employee';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function label(): string
    {
        return config("roles.labels.{$this->slug}", $this->name);
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
        return config('roles.corporate_roles_with_employees', []);
    }

    /**
     * Найти роль по слагу.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}

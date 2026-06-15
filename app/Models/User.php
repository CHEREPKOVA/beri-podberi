<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\CurrentRoleService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_login_ip',
        'last_login_user_agent',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot([
                'company_name',
                'company_type',
                'company_status',
                'company_region',
                'company_legal_name',
                'company_contact_email',
                'company_contact_phone',
                'company_params',
            ])
            ->withTimestamps();
    }

    public function userPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('is_allowed')
            ->withTimestamps();
    }

    public function manufacturerProfile(): HasOne
    {
        return $this->hasOne(ManufacturerProfile::class);
    }

    public function distributorProfile(): HasOne
    {
        return $this->hasOne(DistributorProfile::class);
    }

    public function endCompanyProfile(): HasOne
    {
        return $this->hasOne(EndCompanyProfile::class);
    }

    public function getOrCreateManufacturerProfile(): ManufacturerProfile
    {
        if ($this->manufacturerProfile) {
            return $this->manufacturerProfile;
        }

        return $this->manufacturerProfile()->create([
            'full_name' => $this->roles()
                ->where('slug', Role::SLUG_MANUFACTURER)
                ->first()?->pivot?->company_name ?? $this->name,
            'inn' => '',
        ]);
    }

    public function getOrCreateDistributorProfile(): DistributorProfile
    {
        if ($this->distributorProfile) {
            return $this->distributorProfile;
        }

        return $this->distributorProfile()->create([
            'full_name' => $this->roles()
                ->where('slug', Role::SLUG_DISTRIBUTOR)
                ->first()?->pivot?->company_name ?? $this->name,
            'inn' => '',
        ]);
    }

    public function getOrCreateEndCompanyProfile(): EndCompanyProfile
    {
        if ($this->endCompanyProfile) {
            return $this->endCompanyProfile;
        }

        return $this->endCompanyProfile()->create([
            'full_name' => $this->roles()
                ->where('slug', Role::SLUG_END_COMPANY)
                ->first()?->pivot?->company_name ?? $this->name,
            'inn' => '',
        ]);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->relationLoaded('userPermissions') && $this->userPermissions->isNotEmpty()) {
            $override = $this->userPermissions->firstWhere('slug', $permissionSlug);
            if ($override) {
                return (bool) $override->pivot->is_allowed;
            }
        } else {
            $override = $this->userPermissions()->where('slug', $permissionSlug)->first();
            if ($override) {
                return (bool) $override->pivot->is_allowed;
            }
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('slug', $permissionSlug))
            ->exists();
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Текущая выбранная роль (из сессии). null если не выбрана или не совпадает с ролями пользователя.
     */
    public function getCurrentRole(): ?Role
    {
        return app(CurrentRoleService::class)->get($this);
    }

    /**
     * Установить текущую роль (должна быть в списке ролей пользователя).
     */
    public function setCurrentRole(int $roleId): bool
    {
        return app(CurrentRoleService::class)->set($this, $roleId);
    }

    /**
     * Нужно ли показать экран выбора роли при входе (несколько ролей, текущая не выбрана).
     */
    public function needsRoleSelection(): bool
    {
        return app(CurrentRoleService::class)->needsRoleSelection($this);
    }

    /**
     * Доступные для входа/переключения роли (с учётом статуса привязки к компании).
     *
     * @return Collection<int, Role>
     */
    public function activeRoles(): Collection
    {
        return $this->roles->filter(function (Role $role): bool {
            $status = strtolower((string) ($role->pivot->company_status ?? 'active'));

            return in_array($status, ['', 'active'], true);
        })->values();
    }

    public function currentCompanyRegionName(): ?string
    {
        $regionId = $this->currentCompanyRegionId();
        if ($regionId === null) {
            return null;
        }

        return Region::query()->where('id', $regionId)->value('name');
    }

    public function currentCompanyRegionId(): ?int
    {
        $role = $this->getCurrentRole();
        if (! $role) {
            return null;
        }

        $pivot = $this->roles->firstWhere('id', $role->id)?->pivot;
        $regionName = trim((string) ($pivot?->company_region ?? ''));
        if ($regionName !== '') {
            $regionId = Region::query()->where('name', $regionName)->value('id');
            if ($regionId !== null) {
                return (int) $regionId;
            }
        }

        if (in_array($role->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true)) {
            $regionId = $this->endCompanyProfile?->defaultDeliveryAddress()?->region_id;

            return $regionId !== null ? (int) $regionId : null;
        }

        return null;
    }
}

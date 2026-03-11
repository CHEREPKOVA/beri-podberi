<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\CurrentRoleService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('company_name')
            ->withTimestamps();
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
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
}

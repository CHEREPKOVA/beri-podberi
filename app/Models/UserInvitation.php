<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Приглашение внутреннего сотрудника.
 * Приглашающий (inviter) — администратор корпоративного аккаунта:
 * производитель, дистрибьютор или конечная компания.
 */
class UserInvitation extends Model
    protected $table = 'user_invitations';

    protected $fillable = [
        'email',
        'token',
        'inviter_id',
        'role_id',
        'name',
        'position',
        'permissions',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted();
    }

    /**
     * Создать новый токен приглашения.
     */
    public static function createToken(): string
    {
        return Str::random(64);
    }
}

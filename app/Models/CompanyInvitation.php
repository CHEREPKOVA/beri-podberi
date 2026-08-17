<?php

namespace App\Models;

use App\Support\HashesInvitationToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInvitation extends Model
{
    use HashesInvitationToken;

    public const STATUS_AWAITING = 'awaiting_confirmation';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ACCEPTED = 'accepted';

    public const TTL_DAYS = 3;

    protected $fillable = [
        'email',
        'company_name',
        'company_types',
        'token',
        'inviter_id',
        'user_id',
        'expires_at',
        'sent_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'company_types' => 'array',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @deprecated Используйте createPlainToken() + hashToken()
     */
    public static function createToken(): string
    {
        return self::createPlainToken();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted() && ! $this->isCancelled();
    }

    public function status(): string
    {
        if ($this->isAccepted()) {
            return self::STATUS_ACCEPTED;
        }

        if ($this->isCancelled()) {
            return self::STATUS_CANCELLED;
        }

        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_AWAITING;
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            self::STATUS_ACCEPTED => 'Принято',
            self::STATUS_CANCELLED => 'Отменено',
            self::STATUS_EXPIRED => 'Просрочено',
            default => 'Ожидает подтверждения',
        };
    }

    /**
     * Обновить токен и срок. Возвращает plaintext для письма.
     */
    public function refreshTokenAndExpiry(): string
    {
        $plainToken = self::createPlainToken();

        $this->forceFill([
            'token' => self::hashToken($plainToken),
            'expires_at' => now()->addDays(self::TTL_DAYS),
            'cancelled_at' => null,
            'accepted_at' => null,
        ])->save();

        return $plainToken;
    }
}

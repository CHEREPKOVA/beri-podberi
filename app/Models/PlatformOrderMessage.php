<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformOrderMessage extends Model
{
    use SoftDeletes;

    public const TYPE_USER = 'user';

    public const TYPE_SYSTEM = 'system';

    public const ROLE_BUYER = 'buyer';

    public const ROLE_SUPPLIER = 'supplier';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SYSTEM = 'system';

    public const EVENT_ORDER_CREATED = 'order_created';

    public const EVENT_SUPPLIER_CHANGES = 'supplier_changes';

    public const EVENT_BUYER_APPROVED = 'buyer_approved';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public const EVENT_TTN_ADDED = 'ttn_added';

    protected $fillable = [
        'platform_order_id',
        'author_user_id',
        'author_role',
        'type',
        'body',
        'event_code',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_BUYER => 'Покупатель',
            self::ROLE_SUPPLIER => 'Поставщик',
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_SYSTEM => 'Система',
        ];
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->author_role] ?? $this->author_role;
    }

    public function authorName(): string
    {
        if ($this->isSystem()) {
            return 'Система';
        }

        return $this->author?->name ?: 'Пользователь';
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM || $this->author_role === self::ROLE_SYSTEM;
    }

    public function isUser(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PlatformOrder::class, 'platform_order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PlatformOrderMessageAttachment::class);
    }
}

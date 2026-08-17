<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformOrderClaim extends Model
{
    public const REASON_QUALITY = 'quality';

    public const REASON_QUANTITY = 'quantity';

    public const REASON_DELAY = 'delay';

    public const REASON_DOCUMENT = 'document';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'platform_order_id',
        'created_by_user_id',
        'creator_role',
        'reason',
        'description',
        'claim_status_id',
        'status_slug',
    ];

    /**
     * @return array<string, string>
     */
    public static function reasonLabels(): array
    {
        return [
            self::REASON_QUALITY => 'Качество товара',
            self::REASON_QUANTITY => 'Несоответствие количества',
            self::REASON_DELAY => 'Срыв сроков поставки',
            self::REASON_DOCUMENT => 'Документы / комплектность',
            self::REASON_OTHER => 'Другое',
        ];
    }

    public function reasonLabel(): string
    {
        return self::reasonLabels()[$this->reason] ?? $this->reason;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PlatformOrder::class, 'platform_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function claimStatus(): BelongsTo
    {
        return $this->belongsTo(ClaimStatus::class);
    }
}

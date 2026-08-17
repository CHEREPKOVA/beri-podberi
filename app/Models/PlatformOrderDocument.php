<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlatformOrderDocument extends Model
{
    public const ROLE_SUPPLIER = 'supplier';

    public const ROLE_BUYER = 'buyer';

    public const TYPE_TTN = 'ttn';

    public const TYPE_DISPATCH_NOTE = 'dispatch_note';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_ACCOUNT = 'account';

    public const TYPE_COMMERCIAL_OFFER = 'commercial_offer';

    public const TYPE_CHECKLIST = 'checklist';

    public const TYPE_BATCH_CERTIFICATE = 'batch_certificate';

    public const TYPE_COVER_LETTER = 'cover_letter';

    public const TYPE_UPD = 'upd';

    public const TYPE_ACT = 'act';

    public const TYPE_SUPPLY_CONTRACT = 'supply_contract';

    public const TYPE_SUPPLIER_OTHER = 'supplier_other';

    public const TYPE_RECONCILIATION_ACT = 'reconciliation_act';

    public const TYPE_RECEIPT_CONFIRMATION = 'receipt_confirmation';

    public const TYPE_RETURN_REQUEST = 'return_request';

    public const TYPE_CLAIM = 'claim';

    public const TYPE_BUYER_OTHER = 'buyer_other';

    protected $fillable = [
        'platform_order_id',
        'uploaded_by_user_id',
        'uploader_role',
        'name',
        'type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function supplierTypeLabels(): array
    {
        return [
            self::TYPE_TTN => 'Товарно-транспортная накладная (ТТН)',
            self::TYPE_DISPATCH_NOTE => 'Накладная на отпуск товара',
            self::TYPE_ACCOUNT => 'Счёт',
            self::TYPE_INVOICE => 'Счёт-фактура',
            self::TYPE_COMMERCIAL_OFFER => 'Коммерческое предложение',
            self::TYPE_BATCH_CERTIFICATE => 'Сертификат на партию',
            self::TYPE_COVER_LETTER => 'Сопроводительное письмо',
            self::TYPE_UPD => 'УПД',
            self::TYPE_ACT => 'Акт приёмки',
            self::TYPE_SUPPLY_CONTRACT => 'Договор поставки',
            self::TYPE_CHECKLIST => 'Чек-лист',
            self::TYPE_SUPPLIER_OTHER => 'Прочий документ поставщика',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function buyerTypeLabels(): array
    {
        return [
            self::TYPE_RECONCILIATION_ACT => 'Акт сверки',
            self::TYPE_RECEIPT_CONFIRMATION => 'Подтверждение получения товара',
            self::TYPE_RETURN_REQUEST => 'Заявка на возврат',
            self::TYPE_CLAIM => 'Претензия',
            self::TYPE_BUYER_OTHER => 'Прочий документ покупателя',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return self::supplierTypeLabels() + self::buyerTypeLabels();
    }

    /**
     * @return list<string>
     */
    public static function supplierTypes(): array
    {
        return array_keys(self::supplierTypeLabels());
    }

    /**
     * @return list<string>
     */
    public static function buyerTypes(): array
    {
        return array_keys(self::buyerTypeLabels());
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function uploaderRoleLabel(): string
    {
        return match ($this->uploader_role) {
            self::ROLE_SUPPLIER => 'Поставщик',
            self::ROLE_BUYER => 'Покупатель',
            default => $this->uploader_role,
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PlatformOrder::class, 'platform_order_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function fileExtension(): string
    {
        $name = $this->original_name ?: $this->file_path;

        return strtoupper(pathinfo((string) $name, PATHINFO_EXTENSION) ?: 'FILE');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' МБ';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' КБ';
        }

        return $bytes.' Б';
    }

    public function absolutePath(): string
    {
        return Storage::disk('public')->path($this->file_path);
    }

    public function isPreviewable(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        $ext = strtolower($this->fileExtension());

        if (str_starts_with($mime, 'image/') || $mime === 'application/pdf') {
            return true;
        }

        return in_array($ext, ['PDF', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'], true);
    }

    public function isClaimRelated(): bool
    {
        return in_array($this->type, [self::TYPE_CLAIM, self::TYPE_RETURN_REQUEST], true);
    }
}

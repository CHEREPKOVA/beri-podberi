<?php

namespace App\Models;

use Database\Factories\ManufacturerDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<ManufacturerDocumentFactory> */
class ManufacturerDocument extends Model
{
    use HasFactory;
    public const TYPE_REGISTRATION_CERTIFICATE = 'registration_certificate';
    public const TYPE_COMPANY_CARD = 'company_card';
    public const TYPE_LICENSE = 'license';
    public const TYPE_PRODUCT_CERTIFICATE = 'product_certificate';
    public const TYPE_DISTRIBUTION_AGREEMENT = 'distribution_agreement';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'manufacturer_profile_id',
        'name',
        'type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'valid_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'file_size' => 'integer',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_REGISTRATION_CERTIFICATE => 'Свидетельство о регистрации',
            self::TYPE_COMPANY_CARD => 'Карточка предприятия',
            self::TYPE_LICENSE => 'Лицензия',
            self::TYPE_PRODUCT_CERTIFICATE => 'Сертификат продукции',
            self::TYPE_DISTRIBUTION_AGREEMENT => 'Договор дистрибуции',
            self::TYPE_OTHER => 'Другое',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class, 'manufacturer_profile_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' МБ';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' КБ';
        }
        return $bytes . ' Б';
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->valid_until && $this->valid_until->isFuture() && $this->valid_until->diffInDays(now()) <= $days;
    }
}

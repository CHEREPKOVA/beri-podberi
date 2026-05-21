<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DistributorProductDocument extends Model
{
    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_PASSPORT = 'passport';

    public const TYPE_INSTRUCTION = 'instruction';

    public const TYPE_MANUFACTURER = 'manufacturer';

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'distributor_product_id',
        'name',
        'type',
        'path',
        'original_name',
        'size',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_CERTIFICATE => 'Сертификат',
            self::TYPE_PASSPORT => 'Паспорт',
            self::TYPE_INSTRUCTION => 'Инструкция',
            self::TYPE_MANUFACTURER => 'Файл производителя',
            self::TYPE_INTERNAL => 'Внутренний файл',
            self::TYPE_OTHER => 'Прочее',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DistributorProduct::class, 'distributor_product_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

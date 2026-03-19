<?php

namespace App\Models;

use Database\Factories\ProductDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<ProductDocumentFactory> */
class ProductDocument extends Model
{
    use HasFactory;
    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_INSTRUCTION = 'instruction';
    public const TYPE_DATASHEET = 'datasheet';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'product_id',
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
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_CERTIFICATE => 'Сертификат',
            self::TYPE_INSTRUCTION => 'Инструкция',
            self::TYPE_DATASHEET => 'Техническая документация',
            self::TYPE_OTHER => 'Другое',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getFileSizeForHumansAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

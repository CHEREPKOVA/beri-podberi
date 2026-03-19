<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** @use HasFactory<ProductImageFactory> */
class ProductImage extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'path',
        'original_name',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * URL изображения. Если файла нет в storage (например, создан фабрикой без загрузки),
     * возвращается плейсхолдер.
     */
    public function getUrlAttribute(): string
    {
        if ($this->path && Storage::disk('public')->exists($this->path)) {
            return asset('storage/' . $this->path);
        }

        return asset('images/placeholder-product.svg');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorProductChangeLog extends Model
{
    protected $fillable = [
        'distributor_product_id',
        'action',
        'description',
        'meta',
        'performed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DistributorProduct::class, 'distributor_product_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public static function actionLabels(): array
    {
        return [
            'published' => 'Публикация',
            'hidden' => 'Скрытие',
            'archived' => 'Архив',
            'status_changed' => 'Статус',
            'price_changed' => 'Цена',
            'stock_changed' => 'Остаток',
            'info_updated' => 'Информация',
            'csv_import' => 'Импорт CSV',
            'yml_import' => 'Импорт YML',
            'document_added' => 'Документ',
            'document_removed' => 'Документ',
            'regional_prices_updated' => 'Региональные цены',
        ];
    }

    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'published' => 'bg-green-100 text-green-800',
            'hidden' => 'bg-gray-100 text-gray-700',
            'archived' => 'bg-yellow-100 text-yellow-800',
            'price_changed', 'regional_prices_updated' => 'bg-blue-100 text-blue-800',
            'stock_changed' => 'bg-purple-100 text-purple-800',
            'csv_import' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}

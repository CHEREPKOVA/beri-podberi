<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerDiscountSetLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'discount_set_id',
        'user_id',
        'action',
        'changes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function discountSet(): BelongsTo
    {
        return $this->belongsTo(ManufacturerDiscountSet::class, 'discount_set_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created' => 'Создание',
            'updated' => 'Изменение',
            'activated' => 'Включение',
            'deactivated' => 'Отключение',
            'copied' => 'Копирование',
            'deleted' => 'Удаление',
            default => $this->action,
        };
    }
}

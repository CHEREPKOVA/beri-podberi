<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DocumentType extends Model
{
    public const CONTEXT_MANUFACTURER_PROFILE = 'manufacturer_profile';

    public const CONTEXT_DISTRIBUTOR_PROFILE = 'distributor_profile';

    public const CONTEXT_END_COMPANY_PROFILE = 'end_company_profile';

    public const CONTEXT_PRODUCT = 'product';

    public const CONTEXT_DISTRIBUTOR_PRODUCT = 'distributor_product';

    protected $fillable = [
        'slug',
        'name',
        'context',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForContext($query, string $context)
    {
        return $query->where('context', $context);
    }

    /**
     * @return array<string, string>
     */
    public static function contextLabels(): array
    {
        return [
            self::CONTEXT_MANUFACTURER_PROFILE => 'Профиль производителя',
            self::CONTEXT_DISTRIBUTOR_PROFILE => 'Профиль дистрибьютора',
            self::CONTEXT_END_COMPANY_PROFILE => 'Профиль конечной компании',
            self::CONTEXT_PRODUCT => 'Товар производителя',
            self::CONTEXT_DISTRIBUTOR_PRODUCT => 'Товар дистрибьютора',
        ];
    }

    public function contextLabel(): string
    {
        return self::contextLabels()[$this->context] ?? $this->context;
    }

    /**
     * @return array<string, string>
     */
    public static function labelsMapFor(string $context, array $fallback = []): array
    {
        if (! Schema::hasTable('document_types')) {
            return $fallback;
        }

        $labels = static::query()->active()->forContext($context)->ordered()->pluck('name', 'slug')->all();

        return $labels !== [] ? $labels : $fallback;
    }
}

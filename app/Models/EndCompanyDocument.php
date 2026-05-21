<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndCompanyDocument extends Model
{
    public const TYPE_CHARTER = 'charter';

    public const TYPE_COMPANY_CARD = 'company_card';

    public const TYPE_POWER_OF_ATTORNEY = 'power_of_attorney';

    public const TYPE_REQUISITES_PDF = 'requisites_pdf';

    public const TYPE_CONTRACT = 'contract';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'end_company_profile_id',
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
            self::TYPE_CHARTER => 'Устав / учредительные документы',
            self::TYPE_COMPANY_CARD => 'Карточка предприятия',
            self::TYPE_POWER_OF_ATTORNEY => 'Доверенность',
            self::TYPE_REQUISITES_PDF => 'Реквизиты (PDF)',
            self::TYPE_CONTRACT => 'Договор',
            self::TYPE_OTHER => 'Прочее',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class, 'end_company_profile_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->file_path);
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

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->valid_until && $this->valid_until->isFuture() && $this->valid_until->diffInDays(now()) <= $days;
    }
}

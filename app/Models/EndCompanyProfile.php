<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EndCompanyProfile extends Model
{
    public const LEGAL_FORM_OOO = 'ooo';

    public const LEGAL_FORM_IP = 'ip';

    public const LEGAL_FORM_PAO = 'pao';

    public const LEGAL_FORM_AO = 'ao';

    public const LEGAL_FORM_GOS = 'gos';

    protected $fillable = [
        'user_id',
        'full_name',
        'short_name',
        'legal_form',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'actual_address',
        'director_name',
        'bank_name',
        'bik',
        'checking_account',
        'correspondent_account',
        'logo',
        'description',
        'activity_type',
        'locked_fields',
        'integration_edi_enabled',
        'integration_webhook_url',
        'integration_comment',
    ];

    protected function casts(): array
    {
        return [
            'locked_fields' => 'array',
            'integration_edi_enabled' => 'boolean',
        ];
    }

    public static function legalFormLabels(): array
    {
        return [
            self::LEGAL_FORM_OOO => 'ООО',
            self::LEGAL_FORM_IP => 'ИП',
            self::LEGAL_FORM_PAO => 'ПАО',
            self::LEGAL_FORM_AO => 'АО',
            self::LEGAL_FORM_GOS => 'Гос. учреждение',
        ];
    }

    public function legalFormLabel(): string
    {
        return self::legalFormLabels()[$this->legal_form] ?? $this->legal_form;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EndCompanyContact::class);
    }

    public function primaryContact(): ?EndCompanyContact
    {
        return $this->contacts()->where('is_primary', true)->first();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EndCompanyDocument::class);
    }

    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(EndCompanyDeliveryAddress::class);
    }

    public function profileChanges(): HasMany
    {
        return $this->hasMany(EndCompanyProfileChange::class)->orderByDesc('created_at');
    }

    public function platformOrders(): HasMany
    {
        return $this->hasMany(PlatformOrder::class);
    }

    public function regionIds(): array
    {
        return $this->deliveryAddresses()
            ->whereNotNull('region_id')
            ->pluck('region_id')
            ->unique()
            ->values()
            ->all();
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->full_name;
    }

    public function defaultDeliveryAddress(): ?EndCompanyDeliveryAddress
    {
        return $this->deliveryAddresses()->where('is_default', true)->first()
            ?? $this->deliveryAddresses()->first();
    }

    public function isFieldLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return asset('storage/'.$this->logo);
    }
}

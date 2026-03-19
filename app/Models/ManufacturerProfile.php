<?php

namespace App\Models;

use Database\Factories\ManufacturerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<ManufacturerProfileFactory> */
class ManufacturerProfile extends Model
{
    use HasFactory;

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
        'bank_name',
        'bik',
        'checking_account',
        'correspondent_account',
        'logo',
        'description',
        'delivery_notes',
        'locked_fields',
    ];

    protected function casts(): array
    {
        return [
            'locked_fields' => 'array',
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
        return $this->hasMany(ManufacturerContact::class);
    }

    public function primaryContact(): ?ManufacturerContact
    {
        return $this->contacts()->where('is_primary', true)->first();
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'manufacturer_region')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryRegion(): ?Region
    {
        return $this->regions()->wherePivot('is_primary', true)->first();
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ManufacturerDocument::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function deliveryMethods(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryMethod::class, 'manufacturer_delivery_settings')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function transportCompanies(): BelongsToMany
    {
        return $this->belongsToMany(TransportCompany::class, 'manufacturer_transport_company')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Профили (поставщики), работающие в указанном регионе.
     * Используется для загрузки списка доступных дистрибьюторов для региона.
     */
    public function scopeInRegion($query, ?int $regionId)
    {
        if ($regionId === null) {
            return $query;
        }
        return $query->whereHas('regions', fn ($q) => $q->where('regions.id', $regionId));
    }

    public function isFieldLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }
        return asset('storage/' . $this->logo);
    }
}

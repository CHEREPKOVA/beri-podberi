<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributorProfile extends Model
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
        'bank_name',
        'bik',
        'checking_account',
        'correspondent_account',
        'logo',
        'description',
        'delivery_notes',
        'locked_fields',
        'integration_csv_enabled',
        'integration_yml_enabled',
        'integration_import_1c_stocks',
        'integration_export_orders_1c',
        'integration_csv_feed_url',
        'integration_yml_feed_url',
        'integration_comment',
    ];

    protected function casts(): array
    {
        return [
            'locked_fields' => 'array',
            'integration_csv_enabled' => 'boolean',
            'integration_yml_enabled' => 'boolean',
            'integration_import_1c_stocks' => 'boolean',
            'integration_export_orders_1c' => 'boolean',
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
        return $this->hasMany(DistributorContact::class);
    }

    public function primaryContact(): ?DistributorContact
    {
        return $this->contacts()->where('is_primary', true)->first();
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'distributor_region')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(DistributorWarehouse::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DistributorDocument::class);
    }

    public function deliveryMethods(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryMethod::class, 'distributor_delivery_settings')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function transportCompanies(): BelongsToMany
    {
        return $this->belongsToMany(TransportCompany::class, 'distributor_transport_company')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'distributor_product_category')
            ->withTimestamps();
    }

    public function manufacturerPartnerships(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ManufacturerDistributorPartnership::class);
    }

    public function exclusiveRegionsForManufacturer(int $manufacturerProfileId): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ManufacturerDistributorExclusiveRegion::class)
            ->where('manufacturer_profile_id', $manufacturerProfileId);
    }

    public function platformOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PlatformOrder::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(DistributorProduct::class);
    }

    public function primaryRegion(): ?Region
    {
        return $this->regions()->wherePivot('is_primary', true)->first()
            ?? $this->regions()->first();
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->full_name;
    }

    public function scopeInRegion($query, ?int $regionId)
    {
        if ($regionId === null) {
            return $query;
        }

        return $query->whereHas('regions', fn ($q) => $q->where('regions.id', $regionId));
    }

    /** Профиль заполнен для показа в каталоге партнёров (регион и тип продукции). */
    public function scopeWithCompletePartnerProfile(Builder $query): Builder
    {
        return $query
            ->whereHas('regions')
            ->whereHas('productCategories');
    }

    /**
     * Дистрибьюторы, доступные производителю в общем каталоге партнёров.
     */
    public function scopeVisibleToManufacturer(Builder $query, ManufacturerProfile $manufacturer): Builder
    {
        return $query
            ->whereHas('user.roles', function (Builder $roleQuery): void {
                $roleQuery
                    ->where('roles.slug', Role::SLUG_DISTRIBUTOR)
                    ->where(function (Builder $statusQuery): void {
                        $statusQuery
                            ->whereNull('role_user.company_status')
                            ->orWhere('role_user.company_status', 'active')
                            ->orWhere('role_user.company_status', '');
                    });
            })
            ->where('user_id', '!=', $manufacturer->user_id)
            ->when(filled($manufacturer->inn), function (Builder $q) use ($manufacturer): void {
                $q->where(function (Builder $innQuery) use ($manufacturer): void {
                    $innQuery
                        ->whereNull('inn')
                        ->orWhere('inn', '')
                        ->orWhere('inn', '!=', $manufacturer->inn);
                });
            })
            ->whereDoesntHave('user.manufacturerProfile', function (Builder $mfrQuery) use ($manufacturer): void {
                $mfrQuery->where('manufacturer_profiles.id', $manufacturer->id);
            });
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

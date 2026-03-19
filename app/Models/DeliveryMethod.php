<?php

namespace App\Models;

use Database\Factories\DeliveryMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @use HasFactory<DeliveryMethodFactory> */
class DeliveryMethod extends Model
{
    use HasFactory;
    public const SLUG_SELF_PICKUP = 'self_pickup';
    public const SLUG_TRANSPORT_COMPANY = 'transport_company';
    public const SLUG_OWN_TRANSPORT = 'own_transport';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_tracking',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_tracking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function manufacturerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ManufacturerProfile::class, 'manufacturer_delivery_settings')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

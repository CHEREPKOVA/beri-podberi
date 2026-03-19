<?php

namespace App\Models;

use Database\Factories\TransportCompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @use HasFactory<TransportCompanyFactory> */
class TransportCompany extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'website',
        'tracking_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function manufacturerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ManufacturerProfile::class, 'manufacturer_transport_company')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTrackingLink(string $trackingNumber): ?string
    {
        if (!$this->tracking_url) {
            return null;
        }
        return $this->tracking_url . $trackingNumber;
    }
}

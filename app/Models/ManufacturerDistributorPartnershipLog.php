<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerDistributorPartnershipLog extends Model
{
    public const ACTION_ADDED = 'added';

    public const ACTION_EXCLUSIVE = 'exclusive_assigned';

    public const ACTION_STATUS_CHANGED = 'status_changed';

    public const ACTION_REMOVED = 'removed';

    protected $fillable = [
        'manufacturer_profile_id',
        'distributor_profile_id',
        'action',
        'description',
        'performed_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }
}

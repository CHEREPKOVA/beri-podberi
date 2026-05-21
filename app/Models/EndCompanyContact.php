<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndCompanyContact extends Model
{
    protected $fillable = [
        'end_company_profile_id',
        'full_name',
        'position',
        'email',
        'phone',
        'is_primary',
        'department',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class, 'end_company_profile_id');
    }

    public function canBeDeleted(): bool
    {
        return ! $this->is_primary;
    }
}

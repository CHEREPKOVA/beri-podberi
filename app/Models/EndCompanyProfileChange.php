<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndCompanyProfileChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'end_company_profile_id',
        'user_id',
        'section',
        'summary',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class, 'end_company_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

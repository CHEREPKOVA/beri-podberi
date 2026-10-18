<?php

namespace App\Models;

use App\Support\HashesInvitationToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Приглашение внутреннего сотрудника.
 * Приглашающий (inviter) — администратор корпоративного аккаунта:
 * производитель, дистрибьютор или конечная компания.
 */
class UserInvitation extends Model
{
    use HashesInvitationToken;

    protected $table = 'user_invitations';

    public const TTL_DAYS = 7;

    protected $fillable = [
        'email',
        'token',
        'inviter_id',
        'user_id',
        'manufacturer_profile_id',
        'employee_role_id',
        'distributor_profile_id',
        'distributor_employee_role_id',
        'end_company_profile_id',
        'end_company_employee_role_id',
        'role_id',
        'name',
        'position',
        'permissions',
        'warehouse_ids',
        'expires_at',
        'sent_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'warehouse_ids' => 'array',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manufacturerProfile(): BelongsTo
    {
        return $this->belongsTo(ManufacturerProfile::class);
    }

    public function employeeRole(): BelongsTo
    {
        return $this->belongsTo(ManufacturerEmployeeRole::class, 'employee_role_id');
    }

    public function distributorProfile(): BelongsTo
    {
        return $this->belongsTo(DistributorProfile::class);
    }

    public function distributorEmployeeRole(): BelongsTo
    {
        return $this->belongsTo(DistributorEmployeeRole::class, 'distributor_employee_role_id');
    }

    public function endCompanyProfile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class);
    }

    public function endCompanyEmployeeRole(): BelongsTo
    {
        return $this->belongsTo(EndCompanyEmployeeRole::class, 'end_company_employee_role_id');
    }

    public function companyDisplayName(): string
    {
        return $this->endCompanyProfile?->displayName()
            ?: $this->distributorProfile?->displayName()
            ?: $this->manufacturerProfile?->displayName()
            ?: (string) ($this->name ?? '');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted() && ! $this->isCancelled();
    }

    public function refreshTokenAndExpiry(): string
    {
        $ttl = (int) match (true) {
            (bool) $this->end_company_profile_id => config('end_company_staff.invitation_ttl_days', self::TTL_DAYS),
            (bool) $this->distributor_profile_id => config('distributor_staff.invitation_ttl_days', self::TTL_DAYS),
            default => config('manufacturer_staff.invitation_ttl_days', self::TTL_DAYS),
        };
        $plainToken = self::createPlainToken();

        $this->forceFill([
            'token' => self::hashToken($plainToken),
            'expires_at' => now()->addDays($ttl),
            'cancelled_at' => null,
            'accepted_at' => null,
        ])->save();

        return $plainToken;
    }

    /**
     * @deprecated Используйте createPlainToken() + hashToken()
     */
    public static function createToken(): string
    {
        return self::createPlainToken();
    }
}

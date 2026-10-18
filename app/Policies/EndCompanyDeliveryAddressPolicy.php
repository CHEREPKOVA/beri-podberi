<?php

namespace App\Policies;

use App\Models\EndCompanyDeliveryAddress;
use App\Models\User;
use App\Services\EndCompanyStaffAccess;

class EndCompanyDeliveryAddressPolicy
{
    public function manage(User $user, EndCompanyDeliveryAddress $address): bool
    {
        $profile = $user->currentEndCompanyProfile() ?? $user->endCompanyProfile;

        return (int) $address->end_company_profile_id === (int) $profile?->id
            && app(EndCompanyStaffAccess::class)->canAccessSection($user, 'profile');
    }

    public function update(User $user, EndCompanyDeliveryAddress $address): bool
    {
        return $this->manage($user, $address);
    }

    public function delete(User $user, EndCompanyDeliveryAddress $address): bool
    {
        return $this->manage($user, $address);
    }
}

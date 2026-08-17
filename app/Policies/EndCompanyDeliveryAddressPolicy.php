<?php

namespace App\Policies;

use App\Models\EndCompanyDeliveryAddress;
use App\Models\User;

class EndCompanyDeliveryAddressPolicy
{
    public function manage(User $user, EndCompanyDeliveryAddress $address): bool
    {
        return (int) $address->end_company_profile_id === (int) $user->endCompanyProfile?->id;
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

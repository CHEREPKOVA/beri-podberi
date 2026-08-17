<?php

namespace App\Policies;

use App\Models\DistributorContact;
use App\Models\User;

class DistributorContactPolicy
{
    public function manage(User $user, DistributorContact $contact): bool
    {
        return (int) $contact->distributor_profile_id === (int) $user->distributorProfile?->id;
    }

    public function update(User $user, DistributorContact $contact): bool
    {
        return $this->manage($user, $contact);
    }

    public function delete(User $user, DistributorContact $contact): bool
    {
        return $this->manage($user, $contact);
    }
}

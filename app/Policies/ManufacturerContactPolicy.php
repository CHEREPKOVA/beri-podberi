<?php

namespace App\Policies;

use App\Models\ManufacturerContact;
use App\Models\User;

class ManufacturerContactPolicy
{
    public function manage(User $user, ManufacturerContact $contact): bool
    {
        return (int) $contact->manufacturer_profile_id === (int) $user->manufacturerProfile?->id;
    }

    public function update(User $user, ManufacturerContact $contact): bool
    {
        return $this->manage($user, $contact);
    }

    public function delete(User $user, ManufacturerContact $contact): bool
    {
        return $this->manage($user, $contact);
    }
}

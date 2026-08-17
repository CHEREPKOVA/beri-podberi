<?php

namespace App\Policies;

use App\Models\EndCompanyContact;
use App\Models\User;

class EndCompanyContactPolicy
{
    public function manage(User $user, EndCompanyContact $contact): bool
    {
        return (int) $contact->end_company_profile_id === (int) $user->endCompanyProfile?->id;
    }

    public function update(User $user, EndCompanyContact $contact): bool
    {
        return $this->manage($user, $contact);
    }

    public function delete(User $user, EndCompanyContact $contact): bool
    {
        return $this->manage($user, $contact);
    }
}

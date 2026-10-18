<?php

namespace App\Policies;

use App\Models\EndCompanyContact;
use App\Models\User;
use App\Services\EndCompanyStaffAccess;

class EndCompanyContactPolicy
{
    public function manage(User $user, EndCompanyContact $contact): bool
    {
        $profile = $user->currentEndCompanyProfile() ?? $user->endCompanyProfile;

        return (int) $contact->end_company_profile_id === (int) $profile?->id
            && app(EndCompanyStaffAccess::class)->canAccessSection($user, 'profile');
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

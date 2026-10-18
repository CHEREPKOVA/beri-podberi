<?php

namespace App\Policies;

use App\Models\EndCompanyDocument;
use App\Models\User;
use App\Services\EndCompanyStaffAccess;

class EndCompanyDocumentPolicy
{
    public function manage(User $user, EndCompanyDocument $document): bool
    {
        $profile = $user->currentEndCompanyProfile() ?? $user->endCompanyProfile;

        return (int) $document->end_company_profile_id === (int) $profile?->id
            && app(EndCompanyStaffAccess::class)->canAccessSection($user, 'profile');
    }

    public function delete(User $user, EndCompanyDocument $document): bool
    {
        return $this->manage($user, $document);
    }
}

<?php

namespace App\Policies;

use App\Models\EndCompanyDocument;
use App\Models\User;

class EndCompanyDocumentPolicy
{
    public function manage(User $user, EndCompanyDocument $document): bool
    {
        return (int) $document->end_company_profile_id === (int) $user->endCompanyProfile?->id;
    }

    public function delete(User $user, EndCompanyDocument $document): bool
    {
        return $this->manage($user, $document);
    }
}

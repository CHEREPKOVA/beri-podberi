<?php

namespace App\Policies;

use App\Models\DistributorDocument;
use App\Models\User;

class DistributorDocumentPolicy
{
    public function manage(User $user, DistributorDocument $document): bool
    {
        return (int) $document->distributor_profile_id === (int) $user->distributorProfile?->id;
    }

    public function delete(User $user, DistributorDocument $document): bool
    {
        return $this->manage($user, $document);
    }
}

<?php

namespace App\Policies;

use App\Models\ManufacturerDocument;
use App\Models\User;

class ManufacturerDocumentPolicy
{
    public function manage(User $user, ManufacturerDocument $document): bool
    {
        return (int) $document->manufacturer_profile_id === (int) $user->manufacturerProfile?->id;
    }

    public function delete(User $user, ManufacturerDocument $document): bool
    {
        return $this->manage($user, $document);
    }
}

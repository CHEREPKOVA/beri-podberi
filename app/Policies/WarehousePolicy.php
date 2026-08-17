<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function manage(User $user, Warehouse $warehouse): bool
    {
        return (int) $warehouse->manufacturer_profile_id === (int) $user->manufacturerProfile?->id;
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->manage($user, $warehouse);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->manage($user, $warehouse);
    }
}

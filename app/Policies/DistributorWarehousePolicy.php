<?php

namespace App\Policies;

use App\Models\DistributorWarehouse;
use App\Models\User;

class DistributorWarehousePolicy
{
    public function manage(User $user, DistributorWarehouse $warehouse): bool
    {
        return (int) $warehouse->distributor_profile_id === (int) $user->distributorProfile?->id;
    }

    public function update(User $user, DistributorWarehouse $warehouse): bool
    {
        return $this->manage($user, $warehouse);
    }

    public function delete(User $user, DistributorWarehouse $warehouse): bool
    {
        return $this->manage($user, $warehouse);
    }
}

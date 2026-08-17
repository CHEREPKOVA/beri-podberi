<?php

namespace App\Policies;

use App\Models\DistributorProduct;
use App\Models\User;

class DistributorProductPolicy
{
    public function manage(User $user, DistributorProduct $product): bool
    {
        $profile = $user->getOrCreateDistributorProfile();

        return (int) $product->distributor_profile_id === (int) $profile->id;
    }

    public function view(User $user, DistributorProduct $product): bool
    {
        return $this->manage($user, $product);
    }

    public function update(User $user, DistributorProduct $product): bool
    {
        return $this->manage($user, $product);
    }

    public function delete(User $user, DistributorProduct $product): bool
    {
        return $this->manage($user, $product);
    }
}

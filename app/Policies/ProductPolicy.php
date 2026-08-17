<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function manage(User $user, Product $product): bool
    {
        return (int) $product->manufacturer_profile_id === (int) $user->manufacturerProfile?->id;
    }

    public function view(User $user, Product $product): bool
    {
        return $this->manage($user, $product);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->manage($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->manage($user, $product);
    }
}

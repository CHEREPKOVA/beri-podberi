<?php

namespace App\Services;

use App\Models\DistributorProduct;
use App\Models\DistributorProductChangeLog;
use App\Models\User;

class DistributorProductLogger
{
    public static function log(
        DistributorProduct $product,
        string $action,
        ?string $description = null,
        ?array $meta = null,
        ?User $user = null,
    ): DistributorProductChangeLog {
        return $product->changeLogs()->create([
            'action' => $action,
            'description' => $description,
            'meta' => $meta,
            'performed_by_user_id' => $user?->id,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\DistributorProduct;
use App\Models\DistributorProductChangeLog;
use App\Models\User;

class DistributorProductLogger
{
    public static function logStatusChange(
        DistributorProduct $product,
        string $oldStatus,
        string $newStatus,
        string $action,
        ?User $user = null,
    ): DistributorProductChangeLog {
        $oldLabel = DistributorProduct::statusLabels()[$oldStatus] ?? $oldStatus;
        $newLabel = DistributorProduct::statusLabels()[$newStatus] ?? $newStatus;

        return self::log(
            $product,
            $action,
            sprintf('Статус: %s → %s', $oldLabel, $newLabel),
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'total_stock' => $product->total_stock,
            ],
            $user,
        );
    }

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

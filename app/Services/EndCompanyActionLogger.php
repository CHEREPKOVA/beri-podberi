<?php

namespace App\Services;

use App\Models\EndCompanyActionLog;
use App\Models\EndCompanyProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EndCompanyActionLogger
{
    public function log(
        EndCompanyProfile $profile,
        ?User $actor,
        string $action,
        string $description,
        ?string $targetType = null,
        ?int $targetId = null,
        array $meta = [],
    ): EndCompanyActionLog {
        return EndCompanyActionLog::query()->create([
            'end_company_profile_id' => $profile->id,
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta !== [] ? $meta : null,
            'created_at' => now(),
        ]);
    }

    public function paginateForProfile(EndCompanyProfile $profile, int $perPage = 30): LengthAwarePaginator
    {
        return EndCompanyActionLog::query()
            ->where('end_company_profile_id', $profile->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

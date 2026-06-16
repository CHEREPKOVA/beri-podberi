<?php

namespace App\Services\Catalog;

use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class CatalogRegionService
{
    public const SESSION_KEY = 'catalog_region_id';

    public function resolveRegionId(User $user): ?int
    {
        $sessionRegion = session(self::SESSION_KEY);
        if (is_numeric($sessionRegion)) {
            $regionId = (int) $sessionRegion;
            if ($this->availableRegions($user)->contains('id', $regionId)) {
                return $regionId;
            }
        }

        return $user->currentCompanyRegionId();
    }

    public function setRegionId(User $user, int $regionId): bool
    {
        if (! $this->availableRegions($user)->contains('id', $regionId)) {
            return false;
        }

        session([self::SESSION_KEY => $regionId]);

        return true;
    }

    public function clearRegionOverride(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, Region>
     */
    public function availableRegions(User $user): Collection
    {
        $role = $user->getCurrentRole();

        if (in_array($role?->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true)) {
            $user->loadMissing('endCompanyProfile.deliveryAddresses');
            $regionIds = $user->endCompanyProfile?->deliveryAddresses
                ->pluck('region_id')
                ->filter()
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all() ?? [];

            if ($regionIds === []) {
                $defaultId = $user->currentCompanyRegionId();

                return $defaultId !== null
                    ? Region::query()->whereKey($defaultId)->get()
                    : collect();
            }

            return Region::query()->whereIn('id', $regionIds)->orderBy('name')->get();
        }

        if ($role?->slug === Role::SLUG_DISTRIBUTOR) {
            $user->loadMissing('distributorProfile.regions');

            return $user->distributorProfile?->regions()->orderBy('name')->get() ?? collect();
        }

        return collect();
    }

    public function showRegionSelector(User $user): bool
    {
        return $this->availableRegions($user)->count() > 1;
    }
}

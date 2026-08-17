<?php

namespace App\Services\Order;

use App\Models\DistributorContact;
use App\Models\DistributorProfile;
use App\Models\PlatformOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DistributorOrderListService
{
    /**
     * @return LengthAwarePaginator<int, PlatformOrder>
     */
    public function paginate(DistributorProfile $profile, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = PlatformOrder::query()
            ->where('distributor_profile_id', $profile->id)
            ->where(function (Builder $channel): void {
                $channel->where('order_channel', PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('order_channel')
                            ->whereNotNull('end_company_profile_id');
                    });
            })
            ->with(['endCompanyProfile', 'deliveryMethod', 'responsibleContact'])
            ->withCount('items')
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Builder<PlatformOrder>
     */
    public function filteredQuery(DistributorProfile $profile, Request $request): Builder
    {
        $query = PlatformOrder::query()
            ->where('distributor_profile_id', $profile->id)
            ->where(function (Builder $channel): void {
                $channel->where('order_channel', PlatformOrder::CHANNEL_END_COMPANY_DISTRIBUTOR)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('order_channel')
                            ->whereNotNull('end_company_profile_id');
                    });
            })
            ->with(['endCompanyProfile', 'deliveryMethod', 'responsibleContact', 'items'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        return $query;
    }

    /**
     * @return Collection<int, DistributorContact>
     */
    public function managers(DistributorProfile $profile): Collection
    {
        return $profile->contacts()->orderByDesc('is_primary')->orderBy('full_name')->get();
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('number')) {
            $number = trim((string) $request->string('number'));
            $query->where('order_number', 'like', "%{$number}%");
        }

        if ($request->filled('company')) {
            $company = trim((string) $request->string('company'));
            $query->whereHas('endCompanyProfile', function (Builder $profile) use ($company): void {
                $profile->where('full_name', 'like', "%{$company}%")
                    ->orWhere('short_name', 'like', "%{$company}%")
                    ->orWhere('inn', 'like', "%{$company}%");
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('endCompanyProfile', function (Builder $profile) use ($search): void {
                        $profile->where('full_name', 'like', "%{$search}%")
                            ->orWhere('short_name', 'like', "%{$search}%")
                            ->orWhere('inn', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->string('status');
            if (array_key_exists($status, PlatformOrder::fallbackStatusLabels())) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('responsible_contact_id')) {
            $query->where('responsible_contact_id', (int) $request->input('responsible_contact_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('ordered_at', '>=', (string) $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ordered_at', '<=', (string) $request->string('date_to'));
        }

        if ($request->boolean('attention')) {
            $query->whereIn('status', [
                PlatformOrder::STATUS_NEW,
                PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            ]);
        }
    }
}

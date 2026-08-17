<?php

namespace App\Services\Order;

use App\Models\DistributorContact;
use App\Models\DistributorProfile;
use App\Models\PlatformOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DistributorPurchaseListService
{
    /**
     * @return LengthAwarePaginator<int, PlatformOrder>
     */
    public function paginate(DistributorProfile $profile, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->baseQuery($profile)
            ->with(['manufacturerProfile', 'deliveryMethod', 'responsibleContact'])
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
        $query = $this->baseQuery($profile)
            ->with(['manufacturerProfile', 'deliveryMethod', 'responsibleContact', 'items'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        return $query;
    }

    /**
     * @return Builder<PlatformOrder>
     */
    public function baseQuery(DistributorProfile $profile): Builder
    {
        return PlatformOrder::query()
            ->where('distributor_profile_id', $profile->id)
            ->where('order_channel', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER);
    }

    public function ownsOrder(DistributorProfile $profile, PlatformOrder $order): bool
    {
        return (int) $order->distributor_profile_id === (int) $profile->id
            && $order->isDistributorPurchase();
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

        if ($request->filled('manufacturer')) {
            $manufacturer = trim((string) $request->string('manufacturer'));
            $query->whereHas('manufacturerProfile', function (Builder $profile) use ($manufacturer): void {
                $profile->where('full_name', 'like', "%{$manufacturer}%")
                    ->orWhere('short_name', 'like', "%{$manufacturer}%")
                    ->orWhere('inn', 'like', "%{$manufacturer}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
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

        if ($request->filled('amount_from')) {
            $query->where('total_amount', '>=', (float) $request->input('amount_from'));
        }

        if ($request->filled('amount_to')) {
            $query->where('total_amount', '<=', (float) $request->input('amount_to'));
        }

        if ($request->boolean('attention')) {
            $query->whereIn('status', [
                PlatformOrder::STATUS_NEW,
                PlatformOrder::STATUS_NEEDS_APPROVAL,
                PlatformOrder::STATUS_SHIPPED,
            ]);
        }
    }
}

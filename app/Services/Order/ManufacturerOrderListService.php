<?php

namespace App\Services\Order;

use App\Models\ManufacturerContact;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\Region;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManufacturerOrderListService
{
    /**
     * @return LengthAwarePaginator<int, PlatformOrder>
     */
    public function paginate(ManufacturerProfile $profile, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->baseQuery($profile)
            ->with([
                'endCompanyProfile',
                'distributorProfile',
                'deliveryMethod',
                'manufacturerResponsibleContact',
            ])
            ->withCount('items');

        $this->applyFilters($query, $request, $profile);
        $this->applySort($query, $request);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Builder<PlatformOrder>
     */
    public function filteredQuery(ManufacturerProfile $profile, Request $request): Builder
    {
        $query = $this->baseQuery($profile)
            ->with([
                'endCompanyProfile',
                'distributorProfile',
                'deliveryMethod',
                'manufacturerResponsibleContact',
                'items',
            ]);

        $this->applyFilters($query, $request, $profile);
        $this->applySort($query, $request);

        return $query;
    }

    /**
     * @return Builder<PlatformOrder>
     */
    public function baseQuery(ManufacturerProfile $profile): Builder
    {
        $profileId = (int) $profile->id;

        return PlatformOrder::query()
            ->where(function (Builder $outer) use ($profileId): void {
                $outer->where('manufacturer_profile_id', $profileId)
                    ->orWhereHas('items', function (Builder $items) use ($profileId): void {
                        $items->whereHas('product', function (Builder $product) use ($profileId): void {
                            $product->where('manufacturer_profile_id', $profileId);
                        })->orWhereHas('distributorProduct', function (Builder $offer) use ($profileId): void {
                            $offer->where('manufacturer_profile_id', $profileId);
                        });
                    });
            })
            // Черновики закупок дистрибьютора ещё не отправлены производителю.
            ->where(function (Builder $visible): void {
                $visible->where('order_channel', '!=', PlatformOrder::CHANNEL_DISTRIBUTOR_MANUFACTURER)
                    ->orWhereNull('order_channel')
                    ->orWhere('status', '!=', PlatformOrder::STATUS_NEW);
            });
    }

    public function ownsOrder(ManufacturerProfile $profile, PlatformOrder $order): bool
    {
        if (
            $order->isDistributorPurchase()
            && $order->status === PlatformOrder::STATUS_NEW
        ) {
            return false;
        }

        if ((int) $order->manufacturer_profile_id === (int) $profile->id) {
            return true;
        }

        return $order->items()
            ->where(function (Builder $items) use ($profile): void {
                $items->whereHas('product', function (Builder $product) use ($profile): void {
                    $product->where('manufacturer_profile_id', $profile->id);
                })->orWhereHas('distributorProduct', function (Builder $offer) use ($profile): void {
                    $offer->where('manufacturer_profile_id', $profile->id);
                });
            })
            ->exists();
    }

    /**
     * @return Collection<int, ManufacturerContact>
     */
    public function managers(ManufacturerProfile $profile): Collection
    {
        return $profile->contacts()->orderByDesc('is_primary')->orderBy('full_name')->get();
    }

    /**
     * @return Collection<int, Region>
     */
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get(['id', 'name']);
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->input('sort', 'ordered_at');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'total_amount' => $query->orderBy('total_amount', $dir)->orderByDesc('id'),
            'updated' => $query->orderByRaw('COALESCE(status_changed_at, ordered_at, created_at) '.$dir)->orderByDesc('id'),
            default => $query->orderBy('ordered_at', $dir)->orderByDesc('id'),
        };
    }

    private function applyFilters(Builder $query, Request $request, ManufacturerProfile $profile): void
    {
        if ($request->filled('number')) {
            $number = trim((string) $request->string('number'));
            $query->where('order_number', 'like', "%{$number}%");
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('endCompanyProfile', function (Builder $p) use ($search): void {
                        $p->where('full_name', 'like', "%{$search}%")
                            ->orWhere('short_name', 'like', "%{$search}%")
                            ->orWhere('inn', 'like', "%{$search}%");
                    })
                    ->orWhereHas('distributorProfile', function (Builder $p) use ($search): void {
                        $p->where('full_name', 'like', "%{$search}%")
                            ->orWhere('short_name', 'like', "%{$search}%")
                            ->orWhere('inn', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function (Builder $items) use ($search): void {
                        $items->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('company')) {
            $company = trim((string) $request->string('company'));
            $query->where(function (Builder $inner) use ($company): void {
                $inner->whereHas('endCompanyProfile', function (Builder $p) use ($company): void {
                    $p->where('full_name', 'like', "%{$company}%")
                        ->orWhere('short_name', 'like', "%{$company}%")
                        ->orWhere('inn', 'like', "%{$company}%");
                })->orWhereHas('distributorProfile', function (Builder $p) use ($company): void {
                    $p->where('full_name', 'like', "%{$company}%")
                        ->orWhere('short_name', 'like', "%{$company}%")
                        ->orWhere('inn', 'like', "%{$company}%");
                });
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->string('status');
            if (array_key_exists($status, PlatformOrder::fallbackStatusLabels())) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('client_type')) {
            $type = (string) $request->string('client_type');
            if ($type === 'distributor') {
                $query->whereNotNull('distributor_profile_id');
            } elseif ($type === 'end_company') {
                $query->whereNotNull('end_company_profile_id');
            }
        }

        if ($request->filled('region_id')) {
            $regionId = (int) $request->input('region_id');
            $query->whereHas('deliveryAddress', fn (Builder $a) => $a->where('region_id', $regionId));
        }

        if ($request->filled('amount_from')) {
            $query->where('total_amount', '>=', (float) $request->input('amount_from'));
        }

        if ($request->filled('amount_to')) {
            $query->where('total_amount', '<=', (float) $request->input('amount_to'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('ordered_at', '>=', (string) $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ordered_at', '<=', (string) $request->string('date_to'));
        }

        if ($request->filled('responsible_contact_id')) {
            $query->where('manufacturer_responsible_contact_id', (int) $request->input('responsible_contact_id'));
        }

        if ($request->boolean('mine')) {
            $email = auth()->user()?->email;
            if ($email) {
                $contactIds = $profile->contacts()->where('email', $email)->pluck('id');
                $query->whereIn('manufacturer_responsible_contact_id', $contactIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->boolean('attention')) {
            $query->whereIn('status', [
                PlatformOrder::STATUS_NEW,
                PlatformOrder::STATUS_AWAITING_CONFIRMATION,
            ]);
        }
    }
}

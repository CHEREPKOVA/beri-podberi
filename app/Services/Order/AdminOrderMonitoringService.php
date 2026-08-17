<?php

namespace App\Services\Order;

use App\Models\PlatformOrder;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminOrderMonitoringService
{
    /**
     * @return LengthAwarePaginator<int, PlatformOrder>
     */
    public function paginate(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $pendingHours = (int) SystemSetting::getActiveParsed('timings.order_pending_hours', 24);

        $query = PlatformOrder::query()
            ->with([
                'distributorProfile',
                'endCompanyProfile',
                'deliveryMethod',
            ])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $request, $pendingHours);

        $orders = $query->paginate($perPage)->withQueryString();

        $orders->getCollection()->transform(function (PlatformOrder $order) use ($pendingHours): PlatformOrder {
            $order->setAttribute('problem_flags', $order->problemFlags($pendingHours));

            return $order;
        });

        return $orders;
    }

    /**
     * @return array{all: int, problematic: int, stuck: int, paused: int, claims: int, integration: int, rejected_without_reason: int}
     */
    public function counts(): array
    {
        $pendingHours = (int) SystemSetting::getActiveParsed('timings.order_pending_hours', 24);

        return [
            'all' => PlatformOrder::query()->count(),
            'problematic' => PlatformOrder::query()->problematic($pendingHours)->count(),
            'stuck' => PlatformOrder::query()->stuck($pendingHours)->count(),
            'paused' => PlatformOrder::query()->whereNotNull('paused_at')->count(),
            'claims' => PlatformOrder::query()->where('has_active_claim', true)->count(),
            'integration' => PlatformOrder::query()->where('has_integration_error', true)->count(),
            'rejected_without_reason' => PlatformOrder::query()
                ->where('status', PlatformOrder::STATUS_REJECTED)
                ->where(function (Builder $q): void {
                    $q->whereNull('rejection_reason')->orWhere('rejection_reason', '');
                })
                ->count(),
        ];
    }

    private function applyFilters(Builder $query, Request $request, int $pendingHours): void
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('distributorProfile', function (Builder $profile) use ($search): void {
                        $profile->where('full_name', 'like', "%{$search}%")
                            ->orWhere('short_name', 'like', "%{$search}%")
                            ->orWhere('inn', 'like', "%{$search}%");
                    })
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

        if ($request->filled('date_from')) {
            $query->whereDate('ordered_at', '>=', (string) $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ordered_at', '<=', (string) $request->string('date_to'));
        }

        $problem = (string) $request->string('problem');
        match ($problem) {
            'all' => $query->problematic($pendingHours),
            'stuck' => $query->stuck($pendingHours),
            'paused' => $query->whereNotNull('paused_at'),
            'active_claim' => $query->where('has_active_claim', true),
            'integration_error' => $query->where('has_integration_error', true),
            'rejected_without_reason' => $query
                ->where('status', PlatformOrder::STATUS_REJECTED)
                ->where(function (Builder $q): void {
                    $q->whereNull('rejection_reason')->orWhere('rejection_reason', '');
                }),
            default => null,
        };
    }
}

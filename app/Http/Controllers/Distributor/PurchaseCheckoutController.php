<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StorePurchaseCheckoutRequest;
use App\Models\Role;
use App\Services\Order\DistributorPurchaseCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseCheckoutController extends Controller
{
    public function __construct(
        private readonly DistributorPurchaseCheckoutService $checkout,
    ) {}

    public function create(Request $request, int $manufacturer): View|RedirectResponse
    {
        $this->ensureAccess($request);

        try {
            $prepared = $this->checkout->prepare($request->user(), $manufacturer);
        } catch (ValidationException $e) {
            return redirect()
                ->route('distributor.purchases.cart.index')
                ->withErrors($e->errors());
        }

        $profile = $request->user()->distributorProfile;

        return view('distributor.purchases.checkout', [
            'group' => $prepared['group'],
            'manufacturer' => $prepared['manufacturer'],
            'deliveryMethods' => $prepared['delivery_methods'],
            'transportCompanies' => $prepared['transport_companies'],
            'warehouses' => $prepared['warehouses'],
            'deliveryNotes' => $prepared['delivery_notes'],
            'itemWarnings' => $prepared['item_warnings'],
            'managers' => $profile?->contacts()->orderByDesc('is_primary')->orderBy('full_name')->get() ?? collect(),
        ]);
    }

    public function store(StorePurchaseCheckoutRequest $request, int $manufacturer): RedirectResponse
    {
        $this->ensureAccess($request);

        try {
            $order = $this->checkout->place($request->user(), $manufacturer, $request->validated());
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('distributor.purchases.show', $order)
            ->with('success', 'Заказ создан. Отправьте его производителю на подтверждение.');
    }

    private function ensureAccess(Request $request): void
    {
        abort_unless($request->user()?->hasRole(Role::SLUG_DISTRIBUTOR), 403);
        abort_unless($request->user()->getCurrentRole()?->slug === Role::SLUG_DISTRIBUTOR, 403);
    }
}

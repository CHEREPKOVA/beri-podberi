<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreCheckoutRequest;
use App\Models\DistributorProfile;
use App\Models\Role;
use App\Services\Order\OrderCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderCheckoutService $checkoutService,
    ) {}

    public function create(Request $request, DistributorProfile $distributor): View|RedirectResponse
    {
        $this->ensureBuyerAccess($request);

        try {
            $prepared = $this->checkoutService->prepare($request->user(), (int) $distributor->id);
        } catch (ValidationException $e) {
            return redirect()
                ->route('buyer.cart.index')
                ->withErrors($e->errors());
        }

        return view('buyer.checkout.create', [
            'distributor' => $prepared['distributor'],
            'group' => $prepared['group'],
            'deliveryMethods' => $prepared['delivery_methods'],
            'transportCompanies' => $prepared['transport_companies'],
            'deliveryAddresses' => $prepared['delivery_addresses'],
            'defaultAddressId' => $prepared['default_address_id'],
            'deliveryNotes' => $prepared['delivery_notes'],
            'priceChangeNotices' => $prepared['price_change_notices'],
            'itemWarnings' => $prepared['item_warnings'],
        ]);
    }

    public function store(StoreCheckoutRequest $request, DistributorProfile $distributor): RedirectResponse
    {
        $this->ensureBuyerAccess($request);

        try {
            $order = $this->checkoutService->place(
                $request->user(),
                (int) $distributor->id,
                $request->validated(),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('buyer.orders.show', $order)
            ->with('success', 'Заказ '.$order->order_number.' успешно создан.');
    }

    private function ensureBuyerAccess(Request $request): void
    {
        $role = $request->user()?->getCurrentRole();
        abort_unless(
            in_array($role?->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true),
            403
        );
    }
}

<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreBatchCheckoutRequest;
use App\Http\Requests\Buyer\StoreCheckoutRequest;
use App\Models\DistributorProfile;
use App\Models\PlatformOrder;
use App\Models\Role;
use App\Services\Order\OrderCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderCheckoutService $checkoutService,
    ) {}

    public function batchCreate(Request $request): View|RedirectResponse
    {
        $this->ensureBuyerAccess($request);

        try {
            $prepared = $this->checkoutService->prepareAll($request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('buyer.cart.index')
                ->withErrors($e->errors());
        }

        return view('buyer.checkout.batch', [
            'sections' => $prepared['sections'],
            'blockedItems' => $prepared['blocked_items'],
            'totals' => $prepared['totals'],
        ]);
    }

    public function batchStore(StoreBatchCheckoutRequest $request): RedirectResponse
    {
        $this->ensureBuyerAccess($request);

        try {
            $orders = $this->checkoutService->placeAll(
                $request->user(),
                array_values($request->validated('orders')),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        }

        return $this->redirectAfterPlacement($orders);
    }

    public function create(Request $request, DistributorProfile $distributor): View|RedirectResponse
    {
        $this->ensureBuyerAccess($request);

        try {
            $preparedAll = $this->checkoutService->prepareAll($request->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('buyer.cart.index')
                ->withErrors($e->errors());
        }

        if ($preparedAll['sections']->count() !== 1
            || (int) $preparedAll['sections']->first()['group']['distributor_profile_id'] !== (int) $distributor->id
        ) {
            return redirect()->route('buyer.checkout.batch');
        }

        $prepared = $preparedAll['sections']->first();

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

    /**
     * @param  Collection<int, PlatformOrder>  $orders
     */
    private function redirectAfterPlacement($orders): RedirectResponse
    {
        if ($orders->count() === 1) {
            $order = $orders->first();

            return redirect()
                ->route('buyer.orders.show', $order)
                ->with('success', 'Заказ '.$order->order_number.' успешно создан.');
        }

        return redirect()
            ->route('buyer.orders.index')
            ->with('success', 'Создано заказов: '.$orders->count().'.');
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

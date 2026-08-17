<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StorePurchaseCartItemRequest;
use App\Http\Requests\Distributor\UpdatePurchaseCartItemRequest;
use App\Models\DistributorPurchaseCartItem;
use App\Models\Role;
use App\Services\Cart\DistributorPurchaseCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseCartController extends Controller
{
    public function __construct(
        private readonly DistributorPurchaseCartService $cartService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAccess($request);
        $view = $this->cartService->view($request->user());

        return view('distributor.purchases.cart', [
            'groups' => $view['groups'],
            'totals' => $view['totals'],
            'hasBlockingWarnings' => $view['has_blocking_warnings'],
        ]);
    }

    public function store(StorePurchaseCartItemRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensureAccess($request);

        $qty = (int) ($request->validated('quantity') ?? 1);
        $item = $this->cartService->add($request->user(), (int) $request->validated('product_id'), $qty);
        $item->loadMissing('product');
        $name = $item->product?->name ?: 'Товар';
        $message = sprintf('В корзину закупок добавлено: %s × %d шт.', $name, $qty);
        $count = $this->cartService->itemsCount($request->user());

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'product_name' => $name,
                'added_quantity' => $qty,
                'cart_items_count' => $count,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function update(UpdatePurchaseCartItemRequest $request, DistributorPurchaseCartItem $cartItem): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->cartService->updateQuantity($request->user(), $cartItem, (int) $request->validated('quantity'));

        return redirect()->route('distributor.purchases.cart.index')->with('success', 'Количество обновлено.');
    }

    public function destroy(Request $request, DistributorPurchaseCartItem $cartItem): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->cartService->remove($request->user(), $cartItem);

        return redirect()->route('distributor.purchases.cart.index')->with('success', 'Позиция удалена.');
    }

    private function ensureAccess(Request $request): void
    {
        abort_unless($request->user()?->getCurrentRole()?->slug === Role::SLUG_DISTRIBUTOR, 403);
    }
}

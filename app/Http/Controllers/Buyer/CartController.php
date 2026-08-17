<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreCartItemRequest;
use App\Http\Requests\Buyer\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Role;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureBuyerCartAccess($request);

        $view = $this->cartService->view($request->user());

        return view('buyer.cart.index', [
            'groups' => $view['groups'],
            'totals' => $view['totals'],
            'hasBlockingWarnings' => $view['has_blocking_warnings'],
            'cartLiveUrl' => route('buyer.cart.live'),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $this->ensureBuyerCartAccess($request);

        return response()->json($this->cartService->live($request->user()));
    }

    public function store(StoreCartItemRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensureBuyerCartAccess($request);

        $addedQuantity = (int) ($request->validated('quantity') ?? 1);
        $item = $this->cartService->add(
            $request->user(),
            (int) $request->validated('distributor_product_id'),
            $addedQuantity,
        );

        $item->loadMissing('distributorProduct');
        $productName = $item->distributorProduct?->name ?: 'Товар';
        $cartItemsCount = $this->cartService->itemsCount($request->user());
        $message = sprintf(
            'В корзину добавлено: %s × %d шт.',
            $productName,
            $addedQuantity
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'product_name' => $productName,
                'added_quantity' => $addedQuantity,
                'cart_items_count' => $cartItemsCount,
                'cart_item_id' => $item->id,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message)
            ->with('cart_item_id', $item->id);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $this->ensureBuyerCartAccess($request);

        $this->cartService->updateQuantity(
            $request->user(),
            $cartItem,
            (int) $request->validated('quantity'),
        );

        return redirect()
            ->route('buyer.cart.index')
            ->with('success', 'Количество обновлено.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->ensureBuyerCartAccess($request);

        $this->cartService->remove($request->user(), $cartItem);

        return redirect()
            ->route('buyer.cart.index')
            ->with('success', 'Товар удалён из корзины.');
    }

    private function ensureBuyerCartAccess(Request $request): void
    {
        $role = $request->user()?->getCurrentRole();
        abort_unless(
            in_array($role?->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true),
            403
        );
    }
}

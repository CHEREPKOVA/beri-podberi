<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Concerns\LoadsOrderDetailRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreOrderDocumentRequest;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderDocument;
use App\Models\Role;
use App\Services\Cart\CartService;
use App\Services\Order\OrderDocumentService;
use App\Services\Order\OrderStatusWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    use LoadsOrderDetailRelations;

    public function __construct(
        private readonly OrderStatusWorkflowService $workflow,
        private readonly OrderDocumentService $documents,
        private readonly CartService $cart,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureBuyerAccess($request);

        $profile = $request->user()->endCompanyProfile
            ?? $request->user()->getOrCreateEndCompanyProfile();

        $orders = PlatformOrder::query()
            ->where('end_company_profile_id', $profile->id)
            ->with(['distributorProfile', 'deliveryMethod'])
            ->withCount('items')
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('buyer.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, PlatformOrder $order): View
    {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $order->load($this->orderDetailRelations());

        return view('buyer.orders.show', array_merge([
            'order' => $order,
            'actions' => $this->workflow->availableActions($order, OrderStatusWorkflowService::ACTOR_BUYER),
            'progressIndex' => $this->workflow->progressIndex($order),
            'approvalProposal' => $order->status === PlatformOrder::STATUS_NEEDS_APPROVAL
                ? $this->workflow->latestApprovalProposal($order)
                : null,
            'viewer' => 'buyer',
        ], $this->documentUiContext(
            $request,
            $order,
            PlatformOrderDocument::ROLE_BUYER,
            $this->documents,
            'buyer.orders.documents.download',
            'buyer.orders.documents.store',
            'buyer.orders.documents.destroy',
            'buyer.orders.documents.preview',
        )));
    }

    public function reorder(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        try {
            $result = $this->cart->repeatFromOrder($request->user(), $order);
        } catch (ValidationException $e) {
            return redirect()
                ->route('buyer.orders.show', $order)
                ->withErrors($e->errors());
        }

        $message = "В корзину добавлено позиций: {$result['added']}.";
        if ($result['skipped'] !== []) {
            $message .= ' Пропущено: '.implode('; ', $result['skipped']).'.';
        }

        return redirect()
            ->route('buyer.cart.index')
            ->with('success', $message);
    }

    public function storeDocument(StoreOrderDocumentRequest $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        try {
            $this->documents->store(
                $order,
                $request->user(),
                PlatformOrderDocument::ROLE_BUYER,
                $request->file('file'),
                $request->validated(),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('buyer.orders.show', $order)
            ->with('success', 'Документ загружен.');
    }

    public function downloadDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        return $this->documents->download($document);
    }

    public function previewDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        return $this->documents->preview($document);
    }

    public function destroyDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): RedirectResponse {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        try {
            $this->documents->delete($document, $request->user(), PlatformOrderDocument::ROLE_BUYER);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()
            ->route('buyer.orders.show', $order)
            ->with('success', 'Документ удалён.');
    }

    public function cancel(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->cancelByBuyer(
                $order,
                $request->user(),
                $request->input('reason'),
            );
        });
    }

    public function approveChanges(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->approveChanges($order, $request->user());
        }, 'Изменения приняты. Заказ подтверждён.');
    }

    public function rejectChanges(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->rejectChanges(
                $order,
                $request->user(),
                $request->input('reason'),
            );
        }, 'Изменения отклонены. Заказ возвращён поставщику на согласование.');
    }

    public function confirmReceipt(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->complete(
                $order,
                $request->user(),
                OrderStatusWorkflowService::ACTOR_BUYER,
                $request->input('completion_notes'),
            );
        }, 'Получение подтверждено. Заказ завершён.');
    }

    private function runAction(Request $request, PlatformOrder $order, callable $callback, ?string $success = null): RedirectResponse
    {
        $this->ensureBuyerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        try {
            $callback();
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        $redirect = redirect()->route('buyer.orders.show', $order);

        return $success !== null && $success !== ''
            ? $redirect->with('success', $success)
            : $redirect;
    }

    private function ensureBuyerAccess(Request $request): void
    {
        $role = $request->user()?->getCurrentRole();
        abort_unless(
            in_array($role?->slug, [Role::SLUG_END_COMPANY, Role::SLUG_COMPANY_EMPLOYEE], true),
            403
        );
    }

    private function ensureOwnsOrder(Request $request, PlatformOrder $order): void
    {
        $profile = $request->user()->endCompanyProfile
            ?? $request->user()->getOrCreateEndCompanyProfile();

        abort_unless((int) $order->end_company_profile_id === (int) $profile->id, 403);
    }

    private function ensureDocumentBelongsToOrder(PlatformOrder $order, PlatformOrderDocument $document): void
    {
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);
    }
}

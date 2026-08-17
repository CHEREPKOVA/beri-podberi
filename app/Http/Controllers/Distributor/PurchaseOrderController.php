<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Concerns\LoadsOrderDetailRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StoreOrderDocumentRequest;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderDocument;
use App\Models\PlatformOrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Services\Cart\DistributorPurchaseCartService;
use App\Services\Order\BuyerOrderEditService;
use App\Services\Order\DistributorPurchaseListService;
use App\Services\Order\OrderDocumentService;
use App\Services\Order\OrderStatusWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends Controller
{
    use LoadsOrderDetailRelations;

    public function __construct(
        private readonly OrderStatusWorkflowService $workflow,
        private readonly OrderDocumentService $documents,
        private readonly DistributorPurchaseListService $listService,
        private readonly BuyerOrderEditService $editor,
        private readonly DistributorPurchaseCartService $cart,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAccess($request);
        $profile = $request->user()->distributorProfile;
        abort_unless($profile !== null, 403);

        return view('distributor.purchases.index', [
            'orders' => $this->listService->paginate($profile, $request),
            'managers' => $this->listService->managers($profile),
            'statusLabels' => PlatformOrder::fallbackStatusLabels(),
        ]);
    }

    public function show(Request $request, PlatformOrder $order): View
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        $order->load(array_merge($this->orderDetailRelations(), [
            'manufacturerProfile.contacts',
            'distributorWarehouse.region',
            'responsibleContact',
        ]));

        $profile = $request->user()->distributorProfile;
        $canEditItems = in_array($order->status, [
            PlatformOrder::STATUS_NEW,
            PlatformOrder::STATUS_AWAITING_CONFIRMATION,
        ], true);

        $catalogProducts = $canEditItems
            ? Product::query()
                ->visibleInCatalog()
                ->where('manufacturer_profile_id', $order->manufacturer_profile_id)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'sku', 'base_price', 'min_order_quantity'])
            : collect();

        return view('distributor.purchases.show', array_merge([
            'order' => $order,
            'actions' => $this->workflow->availableActions($order, OrderStatusWorkflowService::ACTOR_BUYER),
            'progressIndex' => $this->workflow->progressIndex($order),
            'approvalProposal' => $order->status === PlatformOrder::STATUS_NEEDS_APPROVAL
                ? $this->workflow->latestApprovalProposal($order)
                : null,
            'viewer' => 'buyer',
            'canEditItems' => $canEditItems,
            'catalogProducts' => $catalogProducts,
            'managers' => $this->listService->managers($profile),
        ], $this->documentUiContext(
            $request,
            $order,
            PlatformOrderDocument::ROLE_BUYER,
            $this->documents,
            'distributor.purchases.documents.download',
            'distributor.purchases.documents.store',
            'distributor.purchases.documents.destroy',
            'distributor.purchases.documents.preview',
        )));
    }

    public function print(Request $request, PlatformOrder $order): View
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        $order->load(array_merge($this->orderDetailRelations(), [
            'manufacturerProfile',
            'distributorWarehouse',
            'responsibleContact',
        ]));

        return view('distributor.purchases.print', ['order' => $order]);
    }

    public function submit(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order): void {
            $this->workflow->submitToSupplier($order, $request->user());
        }, 'Заказ отправлен производителю.');
    }

    public function cancel(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order): void {
            $this->workflow->cancelByBuyer($order, $request->user(), $request->input('reason'));
        }, 'Заказ отменён.');
    }

    public function approveChanges(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order): void {
            $this->workflow->approveChanges($order, $request->user());
        }, 'Изменения приняты. Заказ подтверждён.');
    }

    public function rejectChanges(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order): void {
            $this->workflow->rejectChanges($order, $request->user(), $request->input('reason'));
        }, 'Изменения отклонены.');
    }

    public function confirmReceipt(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order): void {
            $this->workflow->complete(
                $order,
                $request->user(),
                OrderStatusWorkflowService::ACTOR_BUYER,
                $request->input('completion_notes'),
            );
        }, 'Получение подтверждено. Заказ завершён.');
    }

    public function reorder(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        try {
            $result = $this->cart->repeatFromOrder($request->user(), $order);
        } catch (ValidationException $e) {
            return redirect()->route('distributor.purchases.show', $order)->withErrors($e->errors());
        }

        $message = "В корзину закупок добавлено позиций: {$result['added']}.";
        if ($result['skipped'] !== []) {
            $message .= ' Пропущено: '.implode('; ', $result['skipped']).'.';
        }

        return redirect()->route('distributor.purchases.cart.index')->with('success', $message);
    }

    public function updateItem(Request $request, PlatformOrder $order, PlatformOrderItem $item): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        try {
            $this->editor->updateQuantity($order, $request->user(), $item, (int) $data['quantity']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('distributor.purchases.show', $order)->with('success', 'Позиция обновлена.');
    }

    public function destroyItem(Request $request, PlatformOrder $order, PlatformOrderItem $item): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        try {
            $this->editor->removeItem($order, $request->user(), $item);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('distributor.purchases.show', $order)->with('success', 'Позиция удалена.');
    }

    public function storeItem(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        try {
            $this->editor->addProduct(
                $order,
                $request->user(),
                (int) $data['product_id'],
                (int) ($data['quantity'] ?? 1),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('distributor.purchases.show', $order)->with('success', 'Товар добавлен.');
    }

    public function assignResponsible(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        $profile = $request->user()->distributorProfile;
        $data = $request->validate([
            'responsible_contact_id' => ['nullable', 'integer', 'exists:distributor_contacts,id'],
        ]);

        $contactId = $data['responsible_contact_id'] ?? null;
        if ($contactId !== null && ! $profile->contacts()->whereKey($contactId)->exists()) {
            return redirect()->back()->withErrors(['responsible_contact_id' => 'Выберите контакт из списка.']);
        }

        $order->responsible_contact_id = $contactId;
        $order->save();

        return redirect()->route('distributor.purchases.show', $order)->with('success', 'Ответственный обновлён.');
    }

    public function storeDocument(StoreOrderDocumentRequest $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

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

        return redirect()->route('distributor.purchases.show', $order)->with('success', 'Документ загружен.');
    }

    public function downloadDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);

        return $this->documents->download($document);
    }

    public function previewDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);

        return $this->documents->preview($document);
    }

    public function destroyDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): RedirectResponse {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);

        try {
            $this->documents->delete($document, $request->user(), PlatformOrderDocument::ROLE_BUYER);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('distributor.purchases.show', $order)->with('success', 'Документ удалён.');
    }

    private function runAction(Request $request, PlatformOrder $order, callable $callback, string $success): RedirectResponse
    {
        $this->ensureAccess($request);
        $this->ensureOwns($request, $order);

        try {
            $callback();
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('distributor.purchases.show', $order)->with('success', $success);
    }

    private function ensureAccess(Request $request): void
    {
        abort_unless($request->user()?->getCurrentRole()?->slug === Role::SLUG_DISTRIBUTOR, 403);
    }

    private function ensureOwns(Request $request, PlatformOrder $order): void
    {
        $profile = $request->user()->distributorProfile;
        abort_unless($profile !== null && $this->listService->ownsOrder($profile, $order), 403);
    }
}

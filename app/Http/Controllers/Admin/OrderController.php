<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LoadsOrderDetailRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PauseOrderRequest;
use App\Http\Requests\Admin\StoreOrderServiceCommentRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderDocument;
use App\Services\Order\AdminOrderMonitoringService;
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
        private AdminOrderMonitoringService $monitoring,
        private OrderStatusWorkflowService $workflow,
        private OrderDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $orders = $this->monitoring->paginate($request);
        $counts = $this->monitoring->counts();
        $statusLabels = PlatformOrder::statusLabels();
        $problemFlagLabels = PlatformOrder::problemFlagLabels();

        return view('admin.orders.index', compact(
            'orders',
            'counts',
            'statusLabels',
            'problemFlagLabels',
        ));
    }

    public function show(PlatformOrder $order): View
    {
        $order->load($this->orderDetailRelations());

        $actions = $this->workflow->availableActions($order, OrderStatusWorkflowService::ACTOR_ADMIN);
        $progressIndex = $this->workflow->progressIndex($order);
        $statusLabels = PlatformOrder::fallbackStatusLabels();
        $problemFlags = $order->problemFlags();
        $problemFlagLabels = PlatformOrder::problemFlagLabels();
        $approvalProposal = $this->workflow->latestApprovalProposal($order);
        $actionLabels = OrderStatusWorkflowService::actionLabels();

        return view('admin.orders.show', array_merge([
            'order' => $order,
            'actions' => $actions,
            'progressIndex' => $progressIndex,
            'statusLabels' => $statusLabels,
            'problemFlags' => $problemFlags,
            'problemFlagLabels' => $problemFlagLabels,
            'approvalProposal' => $approvalProposal,
            'viewer' => 'admin',
            'action_labels' => $actionLabels,
        ], $this->documentUiContext(
            request(),
            $order,
            PlatformOrderDocument::ROLE_SUPPLIER,
            $this->documents,
            'admin.orders.documents.download',
            null,
            null,
            'admin.orders.documents.preview',
        )));
    }

    public function downloadDocument(
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);

        return $this->documents->download($document);
    }

    public function previewDocument(
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);

        return $this->documents->preview($document);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction(
            $order,
            fn () => $this->workflow->adminChangeStatus(
                $order,
                $request->user(),
                (string) $request->validated('status'),
                (string) $request->validated('comment'),
            ),
            'Статус заказа изменён.',
        );
    }

    public function storeComment(StoreOrderServiceCommentRequest $request, PlatformOrder $order): RedirectResponse
    {
        $notify = $request->boolean('notify_parties');

        return $this->runAction(
            $order,
            fn () => $this->workflow->addServiceComment(
                $order,
                $request->user(),
                (string) $request->validated('comment'),
                $notify,
            ),
            $notify
                ? 'Сообщение отправлено сторонам и записано в журнал.'
                : 'Служебный комментарий добавлен.',
        );
    }

    public function pause(PauseOrderRequest $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction(
            $order,
            fn () => $this->workflow->pause(
                $order,
                $request->user(),
                (string) $request->validated('pause_reason'),
            ),
            'Заказ приостановлен.',
        );
    }

    public function resume(Request $request, PlatformOrder $order): RedirectResponse
    {
        if (! $request->user()?->hasPermission('orders.manage')) {
            abort(403);
        }

        $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->runAction(
            $order,
            fn () => $this->workflow->resume(
                $order,
                $request->user(),
                $request->input('comment'),
            ),
            'Приостановка заказа снята.',
        );
    }

    private function runAction(PlatformOrder $order, callable $callback, string $success): RedirectResponse
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', $success);
    }
}

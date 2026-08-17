<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Concerns\LoadsOrderDetailRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StoreOrderDocumentRequest;
use App\Models\DistributorContact;
use App\Models\DistributorProduct;
use App\Models\DistributorWarehouse;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderDocument;
use App\Models\PlatformOrderStatusLog;
use App\Models\Role;
use App\Models\TransportCompany;
use App\Services\Order\DistributorOrderListService;
use App\Services\Order\OrderDocumentService;
use App\Services\Order\OrderStatusWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    use LoadsOrderDetailRelations;

    public function __construct(
        private readonly OrderStatusWorkflowService $workflow,
        private readonly OrderDocumentService $documents,
        private readonly DistributorOrderListService $listService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureDistributorAccess($request);

        $profile = $request->user()->distributorProfile;
        abort_unless($profile !== null, 403);

        return view('distributor.orders.index', [
            'orders' => $this->listService->paginate($profile, $request),
            'managers' => $this->listService->managers($profile),
            'statusLabels' => PlatformOrder::fallbackStatusLabels(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->ensureDistributorAccess($request);

        $profile = $request->user()->distributorProfile;
        abort_unless($profile !== null, 403);

        $format = strtolower((string) $request->input('format', 'csv'));
        $isXls = in_array($format, ['xlsx', 'xls'], true);
        $filename = 'orders_'.now()->format('Y-m-d_His').($isXls ? '.xls' : '.csv');
        $contentType = $isXls
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        $orders = $this->listService->filteredQuery($profile, $request)->get();

        return response()->stream(function () use ($orders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Номер',
                'Покупатель',
                'Дата',
                'Обновлён',
                'Сумма',
                'Статус',
                'Менеджер',
                'Позиций',
            ], ';');

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->endCompanyProfile?->displayName() ?? '',
                    optional($order->ordered_at)->format('d.m.Y H:i') ?: '',
                    optional($order->lastActivityAt())->format('d.m.Y H:i') ?: '',
                    number_format((float) $order->total_amount, 2, '.', ''),
                    $order->statusLabel(),
                    $order->responsibleContact?->full_name ?? '',
                    (string) $order->items->count(),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function show(Request $request, PlatformOrder $order): View
    {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);

        // ТЗ: открытие карточки поставщиком → «Ожидает подтверждения».
        if ($this->workflow->can($order, OrderStatusWorkflowService::ACTOR_SUPPLIER, OrderStatusWorkflowService::ACTION_OPEN)) {
            $order = $this->workflow->open($order, $request->user());
        }

        $order->load(array_merge($this->orderDetailRelations(), [
            'items.warehouse',
            'responsibleContact',
        ]));

        $profile = $request->user()->distributorProfile;

        $warehouses = DistributorWarehouse::query()
            ->where('distributor_profile_id', $profile->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $catalogProducts = DistributorProduct::query()
            ->with('category:id,name')
            ->where('distributor_profile_id', $profile->id)
            ->where('status', DistributorProduct::STATUS_ACTIVE)
            ->orderBy('name')
            ->limit(500)
            ->get([
                'id',
                'name',
                'internal_sku',
                'manufacturer_sku',
                'retail_price',
                'product_category_id',
                'min_order_quantity',
            ]);

        $managers = $this->listService->managers($profile);

        return view('distributor.orders.show', array_merge([
            'order' => $order,
            'actions' => $this->workflow->availableActions($order, OrderStatusWorkflowService::ACTOR_SUPPLIER),
            'progressIndex' => $this->workflow->progressIndex($order),
            'transportCompanies' => TransportCompany::query()->where('is_active', true)->orderBy('name')->get(),
            'buyerChangesRejection' => $this->workflow->latestBuyerChangesRejection($order),
            'viewer' => 'supplier',
            'warehouses' => $warehouses,
            'catalogProducts' => $catalogProducts,
            'managers' => $managers,
            'sourceLabels' => PlatformOrder::sourceLabels(),
        ], $this->documentUiContext(
            $request,
            $order,
            PlatformOrderDocument::ROLE_SUPPLIER,
            $this->documents,
            'distributor.orders.documents.download',
            'distributor.orders.documents.store',
            'distributor.orders.documents.destroy',
            'distributor.orders.documents.preview',
        )));
    }

    public function print(Request $request, PlatformOrder $order): View
    {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $order->load(array_merge($this->orderDetailRelations(), [
            'items.warehouse',
            'responsibleContact',
        ]));

        return view('distributor.orders.print', [
            'order' => $order,
        ]);
    }

    public function assignResponsible(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'responsible_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('distributor_contacts', 'id')->where(
                    fn ($query) => $query->where('distributor_profile_id', $profile->id)
                ),
            ],
        ]);

        $previousId = $order->responsible_contact_id;
        $newId = $validated['responsible_contact_id'] ?? null;

        $order->responsible_contact_id = $newId;
        $order->save();

        $contact = $newId
            ? DistributorContact::query()->find($newId)
            : null;

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'action' => 'assign_responsible',
            'comment' => $contact
                ? 'Назначен ответственный: '.$contact->full_name
                : 'Ответственный снят',
            'performed_by_user_id' => $request->user()->id,
            'meta' => [
                'actor' => OrderStatusWorkflowService::ACTOR_SUPPLIER,
                'previous_contact_id' => $previousId,
                'responsible_contact_id' => $newId,
            ],
        ]);

        return redirect()
            ->route('distributor.orders.show', $order)
            ->with('success', 'Ответственный менеджер обновлён.');
    }

    public function storeDocument(StoreOrderDocumentRequest $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);

        try {
            $this->documents->store(
                $order,
                $request->user(),
                PlatformOrderDocument::ROLE_SUPPLIER,
                $request->file('file'),
                $request->validated(),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('distributor.orders.show', $order)
            ->with('success', 'Документ загружен.');
    }

    public function downloadDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        return $this->documents->download($document);
    }

    public function previewDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        return $this->documents->preview($document);
    }

    public function destroyDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): RedirectResponse {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        try {
            $this->documents->delete($document, $request->user(), PlatformOrderDocument::ROLE_SUPPLIER);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()
            ->route('distributor.orders.show', $order)
            ->with('success', 'Документ удалён.');
    }

    public function confirm(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->confirm($order, $request->user());
        }, 'Заказ подтверждён.');
    }

    public function reject(Request $request, PlatformOrder $order): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'rejection_reason.required' => 'Укажите причину отклонения.',
        ]);

        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->reject($order, $request->user(), $request->input('rejection_reason'));
        }, 'Заказ отклонён.');
    }

    public function sendForApproval(Request $request, PlatformOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.distributor_product_id' => ['nullable', 'integer'],
            'items.*.distributor_warehouse_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.delete' => ['nullable', 'boolean'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->runAction($request, $order, function () use ($request, $order, $validated) {
            $this->workflow->sendForApproval(
                $order,
                $request->user(),
                $validated['items'],
                $validated['comment'] ?? null,
            );
        }, 'Изменения отправлены покупателю на согласование.');
    }

    public function markInWork(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->markInWork($order, $request->user());
        }, 'Заказ передан в работу.');
    }

    public function markReady(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->markReady($order, $request->user());
        }, 'Заказ отмечен как готовый к отгрузке.');
    }

    public function markShipped(Request $request, PlatformOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:128'],
            'shipped_at' => ['nullable', 'date'],
            'shipping_from_warehouse' => ['nullable', 'string', 'max:255'],
            'transport_company_id' => ['nullable', 'integer', 'exists:transport_companies,id'],
        ], [
            'tracking_number.required' => 'Укажите номер ТТН / трек-номер.',
        ]);

        return $this->runAction($request, $order, function () use ($request, $order, $validated) {
            $this->workflow->markShipped($order, $request->user(), $validated);
        }, 'Заказ отмечен как отгруженный.');
    }

    public function complete(Request $request, PlatformOrder $order): RedirectResponse
    {
        return $this->runAction($request, $order, function () use ($request, $order) {
            $this->workflow->complete(
                $order,
                $request->user(),
                OrderStatusWorkflowService::ACTOR_SUPPLIER,
                $request->input('completion_notes'),
            );
        }, 'Заказ завершён.');
    }

    private function runAction(Request $request, PlatformOrder $order, callable $callback, string $success): RedirectResponse
    {
        $this->ensureDistributorAccess($request);
        $this->ensureOwnsOrder($request, $order);

        try {
            $callback();
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('distributor.orders.show', $order)
            ->with('success', $success);
    }

    private function ensureDistributorAccess(Request $request): void
    {
        $role = $request->user()?->getCurrentRole();
        abort_unless($role?->slug === Role::SLUG_DISTRIBUTOR, 403);
    }

    private function ensureOwnsOrder(Request $request, PlatformOrder $order): void
    {
        $profileId = $request->user()->distributorProfile?->id;
        abort_unless(
            $profileId !== null
            && (int) $order->distributor_profile_id === (int) $profileId
            && $order->isEndCompanySale(),
            403
        );
    }

    private function ensureDocumentBelongsToOrder(PlatformOrder $order, PlatformOrderDocument $document): void
    {
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);
    }
}

<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Concerns\LoadsOrderDetailRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturer\StoreOrderDocumentRequest;
use App\Models\ClaimStatus;
use App\Models\DistributorProduct;
use App\Models\ManufacturerContact;
use App\Models\PlatformOrder;
use App\Models\PlatformOrderClaim;
use App\Models\PlatformOrderDocument;
use App\Models\PlatformOrderStatusLog;
use App\Models\Role;
use App\Models\TransportCompany;
use App\Models\Warehouse;
use App\Services\Order\ManufacturerOrderListService;
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
        private readonly ManufacturerOrderListService $listService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureManufacturerAccess($request);

        $profile = $request->user()->manufacturerProfile;
        abort_unless($profile !== null, 403);

        return view('manufacturer.orders.index', [
            'orders' => $this->listService->paginate($profile, $request),
            'managers' => $this->listService->managers($profile),
            'regions' => $this->listService->regions(),
            'statusLabels' => PlatformOrder::fallbackStatusLabels(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->ensureManufacturerAccess($request);

        $profile = $request->user()->manufacturerProfile;
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
                'Дистрибьютор',
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
                    $order->distributorProfile?->displayName() ?? '',
                    optional($order->ordered_at)->format('d.m.Y H:i') ?: '',
                    optional($order->lastActivityAt())->format('d.m.Y H:i') ?: '',
                    number_format((float) $order->total_amount, 2, '.', ''),
                    $order->statusLabel(),
                    $order->manufacturerResponsibleContact?->full_name ?? '',
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
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        // ТЗ: открытие карточки поставщиком → «Ожидает подтверждения».
        if ($this->workflow->can($order, OrderStatusWorkflowService::ACTOR_SUPPLIER, OrderStatusWorkflowService::ACTION_OPEN)) {
            $order = $this->workflow->open($order, $request->user());
        }

        $order->load(array_merge($this->orderDetailRelations(), [
            'items.warehouse',
            'manufacturerResponsibleContact',
            'claims.claimStatus',
        ]));

        $profile = $request->user()->manufacturerProfile;

        $warehouses = Warehouse::query()
            ->where('manufacturer_profile_id', $profile->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $catalogProducts = DistributorProduct::query()
            ->with('category:id,name')
            ->where('manufacturer_profile_id', $profile->id)
            ->where('distributor_profile_id', $order->distributor_profile_id)
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

        return view('manufacturer.orders.show', array_merge([
            'order' => $order,
            'actions' => $this->workflow->availableActions($order, OrderStatusWorkflowService::ACTOR_SUPPLIER),
            'progressIndex' => $this->workflow->progressIndex($order),
            'transportCompanies' => TransportCompany::query()->where('is_active', true)->orderBy('name')->get(),
            'buyerChangesRejection' => $this->workflow->latestBuyerChangesRejection($order),
            'viewer' => 'supplier',
            'warehouses' => $warehouses,
            'catalogProducts' => $catalogProducts,
            'managers' => $managers,
            'claimReasons' => PlatformOrderClaim::reasonLabels(),
            'claimStatuses' => ClaimStatus::labelsMap(),
        ], $this->documentUiContext(
            $request,
            $order,
            PlatformOrderDocument::ROLE_SUPPLIER,
            $this->documents,
            'manufacturer.orders.documents.download',
            'manufacturer.orders.documents.store',
            'manufacturer.orders.documents.destroy',
            'manufacturer.orders.documents.preview',
        )));
    }

    public function print(Request $request, PlatformOrder $order): View
    {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $order->load(array_merge($this->orderDetailRelations(), [
            'items.warehouse',
            'manufacturerResponsibleContact',
        ]));

        return view('manufacturer.orders.print', [
            'order' => $order,
        ]);
    }

    public function exportHistory(Request $request, PlatformOrder $order): StreamedResponse
    {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $order->load(['statusLogs.performedBy']);
        $actionLabels = OrderStatusWorkflowService::actionLabels();
        $statusLabels = PlatformOrder::fallbackStatusLabels();
        $filename = 'order_'.$order->order_number.'_history_'.now()->format('Y-m-d_His').'.csv';

        return response()->stream(function () use ($order, $actionLabels, $statusLabels) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'Дата',
                'Действие',
                'Из статуса',
                'В статус',
                'Комментарий',
                'Пользователь',
            ], ';');

            foreach ($order->statusLogs as $log) {
                fputcsv($file, [
                    optional($log->created_at)->format('d.m.Y H:i') ?: '',
                    $actionLabels[$log->action] ?? ($log->action ?: ''),
                    $statusLabels[$log->from_status] ?? ($log->from_status ?: ''),
                    $statusLabels[$log->to_status] ?? ($log->to_status ?: ''),
                    (string) ($log->comment ?? ''),
                    $log->performedBy?->name ?? '',
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function assignResponsible(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $profile = $request->user()->manufacturerProfile;

        $validated = $request->validate([
            'manufacturer_responsible_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('manufacturer_contacts', 'id')->where(
                    fn ($query) => $query->where('manufacturer_profile_id', $profile->id)
                ),
            ],
        ]);

        $previousId = $order->manufacturer_responsible_contact_id;
        $newId = $validated['manufacturer_responsible_contact_id'] ?? null;

        $order->manufacturer_responsible_contact_id = $newId;
        $order->save();

        $contact = $newId
            ? ManufacturerContact::query()->find($newId)
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
                'manufacturer_responsible_contact_id' => $newId,
            ],
        ]);

        return redirect()
            ->route('manufacturer.orders.show', $order)
            ->with('success', 'Ответственный менеджер обновлён.');
    }

    public function storeClaim(Request $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(PlatformOrderClaim::reasonLabels()))],
            'description' => ['required', 'string', 'max:5000'],
        ], [
            'reason.required' => 'Выберите причину претензии.',
            'description.required' => 'Опишите претензию.',
        ]);

        $claimStatus = ClaimStatus::query()->active()->ordered()->first();

        PlatformOrderClaim::query()->create([
            'platform_order_id' => $order->id,
            'created_by_user_id' => $request->user()->id,
            'creator_role' => 'manufacturer',
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'claim_status_id' => $claimStatus?->id,
            'status_slug' => $claimStatus?->slug,
        ]);

        $order->has_active_claim = true;
        $order->save();

        PlatformOrderStatusLog::query()->create([
            'platform_order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'action' => 'create_claim',
            'comment' => PlatformOrderClaim::reasonLabels()[$validated['reason']].': '.$validated['description'],
            'performed_by_user_id' => $request->user()->id,
            'meta' => [
                'actor' => OrderStatusWorkflowService::ACTOR_SUPPLIER,
                'reason' => $validated['reason'],
            ],
        ]);

        return redirect()
            ->route('manufacturer.orders.show', $order)
            ->with('success', 'Претензия зарегистрирована.');
    }

    public function storeDocument(StoreOrderDocumentRequest $request, PlatformOrder $order): RedirectResponse
    {
        $this->ensureManufacturerAccess($request);
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
            ->route('manufacturer.orders.show', $order)
            ->with('success', 'Документ загружен.');
    }

    public function downloadDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        return $this->documents->download($document);
    }

    public function previewDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): StreamedResponse {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        return $this->documents->preview($document);
    }

    public function destroyDocument(
        Request $request,
        PlatformOrder $order,
        PlatformOrderDocument $document,
    ): RedirectResponse {
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);
        $this->ensureDocumentBelongsToOrder($order, $document);

        try {
            $this->documents->delete($document, $request->user(), PlatformOrderDocument::ROLE_SUPPLIER);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()
            ->route('manufacturer.orders.show', $order)
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
        $this->ensureManufacturerAccess($request);
        $this->ensureOwnsOrder($request, $order);

        try {
            $callback();
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('manufacturer.orders.show', $order)
            ->with('success', $success);
    }

    private function ensureManufacturerAccess(Request $request): void
    {
        $role = $request->user()?->getCurrentRole();
        abort_unless($role?->slug === Role::SLUG_MANUFACTURER, 403);
    }

    private function ensureOwnsOrder(Request $request, PlatformOrder $order): void
    {
        $profile = $request->user()->manufacturerProfile;
        abort_unless($profile !== null && $this->listService->ownsOrder($profile, $order), 403);
    }

    private function ensureDocumentBelongsToOrder(PlatformOrder $order, PlatformOrderDocument $document): void
    {
        abort_unless((int) $document->platform_order_id === (int) $order->id, 404);
    }
}

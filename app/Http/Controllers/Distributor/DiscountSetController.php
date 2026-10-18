<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StoreDistributorDiscountSetRequest;
use App\Http\Requests\Distributor\UpdateDistributorDiscountSetRequest;
use App\Models\DistributorDiscountSet;
use App\Models\DistributorProduct;
use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Services\Discount\DistributorDiscountEngine;
use App\Services\Discount\DistributorDiscountSetService;
use App\Services\DistributorCompanyContext;
use App\Services\DistributorStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscountSetController extends Controller
{
    public function __construct(
        private readonly DistributorCompanyContext $context,
        private readonly DistributorStaffAccess $access,
        private readonly DistributorDiscountSetService $sets,
        private readonly DistributorDiscountEngine $engine,
    ) {}

    public function index(Request $request): View
    {
        $profile = $this->requireProfile($request);
        $data = $this->sets->paginate($profile, $request);

        return view('distributor.discounts.index', [
            'sets' => $data['sets'],
            'rows' => $data['rows'],
            'types' => config('distributor_discounts.types'),
            'statuses' => config('distributor_discounts.statuses'),
            'periods' => config('distributor_discounts.periods'),
            'sources' => config('distributor_discounts.sources'),
            'canManage' => $this->access->canAccessSection($request->user(), 'discounts.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $profile = $this->requireProfile($request);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);

        return view('distributor.discounts.edit', array_merge(
            $this->sets->formCatalog($profile),
            [
                'set' => null,
                'usedInOrders' => false,
                'canManage' => true,
            ]
        ));
    }

    public function store(StoreDistributorDiscountSetRequest $request): RedirectResponse
    {
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $profile = $this->requireProfile($request);
        $data = $request->validated();
        $payload = $this->sets->normalizePayload($data);
        $conflicts = $this->sets->findConflicts($profile, $payload);

        if ($conflicts !== [] && ! $request->boolean('confirm_conflicts')) {
            return back()
                ->withInput()
                ->with('conflict_warning', $this->sets->conflictMessage($conflicts));
        }

        $set = $this->sets->create($profile, $request->user(), $data);

        return redirect()
            ->route('distributor.discounts.edit', $set)
            ->with('success', $request->boolean('activate')
                ? 'Набор скидок сохранён и активирован.'
                : 'Набор скидок сохранён.');
    }

    public function show(Request $request, DistributorDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);

        return redirect()->route('distributor.discounts.edit', $discountSet);
    }

    public function edit(Request $request, DistributorDiscountSet $discountSet): View
    {
        $this->assertOwns($request, $discountSet);
        $profile = $this->requireProfile($request);
        $discountSet->load(['logs.user', 'createdBy', 'updatedBy']);

        return view('distributor.discounts.edit', array_merge(
            $this->sets->formCatalog($profile, $discountSet),
            [
                'set' => $discountSet,
                'usedInOrders' => $this->sets->isUsedInActiveOrders($discountSet),
                'canManage' => $this->access->canAccessSection($request->user(), 'discounts.manage'),
            ]
        ));
    }

    public function update(UpdateDistributorDiscountSetRequest $request, DistributorDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);

        $data = $request->validated();
        $payload = $this->sets->normalizePayload($data);
        $conflicts = $this->sets->findConflicts($discountSet->distributorProfile, $payload, (int) $discountSet->id);

        if ($conflicts !== [] && ! $request->boolean('confirm_conflicts')) {
            return back()
                ->withInput()
                ->with('conflict_warning', $this->sets->conflictMessage($conflicts));
        }

        $this->sets->update($discountSet, $request->user(), $data);

        return redirect()
            ->route('distributor.discounts.edit', $discountSet)
            ->with('success', $request->boolean('activate')
                ? 'Изменения сохранены, набор активирован.'
                : 'Изменения набора скидок сохранены.');
    }

    public function toggle(Request $request, DistributorDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $this->sets->toggle($discountSet, $request->user());
        $fresh = $discountSet->fresh();

        return back()->with('success', $fresh->status === DistributorDiscountSet::STATUS_ACTIVE
            ? 'Набор скидок активирован.'
            : 'Набор скидок отключён.');
    }

    public function copy(Request $request, DistributorDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $copy = $this->sets->duplicate($discountSet, $request->user());

        return redirect()
            ->route('distributor.discounts.edit', $copy)
            ->with('success', 'Создана копия набора скидок.');
    }

    public function destroy(Request $request, DistributorDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);

        try {
            $this->sets->delete($discountSet, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('distributor.discounts.index')
            ->with('success', 'Набор скидок удалён.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $profile = $this->requireProfile($request);
        $action = (string) $request->input('action');
        abort_unless(in_array($action, ['disable', 'delete'], true), 422);

        $ids = array_map('intval', (array) $request->input('ids', []));
        $result = $this->sets->bulk($profile, $request->user(), $ids, $action);

        $message = $action === 'delete'
            ? 'Удалено наборов: '.$result['deleted'].($result['skipped'] ? '. Пропущено (используются в заказах): '.$result['skipped'] : '')
            : 'Отключено наборов: '.$result['disabled'];

        return back()->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->sets->export($this->requireProfile($request));
    }

    public function preview(Request $request, DistributorDiscountSet $discountSet): JsonResponse
    {
        $this->assertOwns($request, $discountSet);
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'region_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $company = EndCompanyProfile::query()->findOrFail((int) $data['company_id']);
        $product = DistributorProduct::query()
            ->where('distributor_profile_id', $discountSet->distributor_profile_id)
            ->findOrFail((int) $data['product_id']);

        $result = $this->engine->preview(
            $discountSet,
            $company,
            $product,
            (int) $data['quantity'],
            isset($data['region_id']) ? (int) $data['region_id'] : null,
            isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
        );

        return response()->json($result);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $profile = $this->requireProfile($request);

        return response()->json(
            $this->sets->searchProducts($profile, (string) $request->input('q', ''))
        );
    }

    public function searchClients(Request $request): JsonResponse
    {
        $this->requireProfile($request);

        return response()->json(
            $this->sets->searchClients((string) $request->input('q', ''))
        );
    }

    private function requireProfile(Request $request): DistributorProfile
    {
        $profile = $this->context->profile($request->user());
        abort_if($profile === null, 403);

        return $profile;
    }

    private function assertOwns(Request $request, DistributorDiscountSet $discountSet): void
    {
        $profile = $this->requireProfile($request);
        abort_unless((int) $discountSet->distributor_profile_id === (int) $profile->id, 404);
    }
}

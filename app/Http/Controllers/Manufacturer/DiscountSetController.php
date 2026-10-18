<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturer\StoreManufacturerDiscountSetRequest;
use App\Http\Requests\Manufacturer\UpdateManufacturerDiscountSetRequest;
use App\Models\DistributorProfile;
use App\Models\ManufacturerDiscountSet;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Services\Discount\DiscountEngine;
use App\Services\Discount\ManufacturerDiscountSetService;
use App\Services\ManufacturerCompanyContext;
use App\Services\ManufacturerStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscountSetController extends Controller
{
    public function __construct(
        private readonly ManufacturerCompanyContext $context,
        private readonly ManufacturerStaffAccess $access,
        private readonly ManufacturerDiscountSetService $sets,
        private readonly DiscountEngine $engine,
    ) {}

    public function index(Request $request): View
    {
        $profile = $this->requireProfile($request);
        $data = $this->sets->paginate($profile, $request);

        return view('manufacturer.discounts.index', [
            'sets' => $data['sets'],
            'rows' => $data['rows'],
            'types' => config('manufacturer_discounts.types'),
            'statuses' => config('manufacturer_discounts.statuses'),
            'distributors' => $this->sets->partnerDistributors($profile),
            'canManage' => $this->access->canAccessSection($request->user(), 'discounts.manage'),
            'canDelete' => $this->access->canAccessSection($request->user(), 'discounts.delete'),
        ]);
    }

    public function create(Request $request): View
    {
        $profile = $this->requireProfile($request);

        return view('manufacturer.discounts.edit', array_merge(
            $this->sets->formCatalog($profile),
            [
                'set' => null,
                'canDelete' => false,
                'usedInOrders' => false,
                'canManage' => true,
            ]
        ));
    }

    public function store(StoreManufacturerDiscountSetRequest $request): RedirectResponse
    {
        $profile = $this->requireProfile($request);
        $data = $request->validated();
        $payload = $this->sets->normalizePayload($profile, $data);
        $conflicts = $this->sets->findConflicts($profile, $payload);

        if ($conflicts !== [] && ! $request->boolean('confirm_conflicts')) {
            return back()
                ->withInput()
                ->with('conflict_warning', $this->sets->conflictMessage($conflicts));
        }

        $set = $this->sets->create($profile, $request->user(), $data);

        return redirect()
            ->route('manufacturer.discounts.edit', $set)
            ->with('success', 'Набор скидок сохранён.');
    }

    public function show(Request $request, ManufacturerDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);

        return redirect()->route('manufacturer.discounts.edit', $discountSet);
    }

    public function edit(Request $request, ManufacturerDiscountSet $discountSet): View
    {
        $this->assertOwns($request, $discountSet);
        $profile = $this->requireProfile($request);
        $discountSet->load(['logs.user', 'createdBy', 'updatedBy']);

        return view('manufacturer.discounts.edit', array_merge(
            $this->sets->formCatalog($profile, $discountSet),
            [
                'set' => $discountSet,
                'canDelete' => $this->access->canAccessSection($request->user(), 'discounts.delete'),
                'usedInOrders' => $this->sets->isUsedInActiveOrders($discountSet),
                'canManage' => $this->access->canAccessSection($request->user(), 'discounts.manage'),
            ]
        ));
    }

    public function update(UpdateManufacturerDiscountSetRequest $request, ManufacturerDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);

        $data = $request->validated();
        $payload = $this->sets->normalizePayload($discountSet->manufacturerProfile, $data);
        $conflicts = $this->sets->findConflicts($discountSet->manufacturerProfile, $payload, (int) $discountSet->id);

        if ($conflicts !== [] && ! $request->boolean('confirm_conflicts')) {
            return back()
                ->withInput()
                ->with('conflict_warning', $this->sets->conflictMessage($conflicts));
        }

        $this->sets->update($discountSet, $request->user(), $data);

        return redirect()
            ->route('manufacturer.discounts.edit', $discountSet)
            ->with('success', 'Изменения набора скидок сохранены.');
    }

    public function toggle(Request $request, ManufacturerDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $this->sets->toggle($discountSet, $request->user());

        return back()->with('success', $discountSet->fresh()->status === ManufacturerDiscountSet::STATUS_ACTIVE
            ? 'Набор скидок включён.'
            : 'Набор скидок отключён.');
    }

    public function copy(Request $request, ManufacturerDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $copy = $this->sets->duplicate($discountSet, $request->user());

        return redirect()
            ->route('manufacturer.discounts.edit', $copy)
            ->with('success', 'Создана копия набора скидок.');
    }

    public function destroy(Request $request, ManufacturerDiscountSet $discountSet): RedirectResponse
    {
        $this->assertOwns($request, $discountSet);

        try {
            $this->sets->delete($discountSet, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('manufacturer.discounts.index')
            ->with('success', 'Набор скидок удалён.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $profile = $this->requireProfile($request);
        $action = (string) $request->input('action');
        abort_unless(in_array($action, ['disable', 'delete'], true), 422);
        if ($action === 'delete') {
            abort_unless($this->access->canAccessSection($request->user(), 'discounts.delete'), 403);
        }

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

    public function preview(Request $request, ManufacturerDiscountSet $discountSet): JsonResponse
    {
        $this->assertOwns($request, $discountSet);
        $data = $request->validate([
            'distributor_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'region_id' => ['nullable', 'integer'],
        ]);

        $distributor = DistributorProfile::query()->findOrFail((int) $data['distributor_id']);
        $product = Product::query()
            ->where('manufacturer_profile_id', $discountSet->manufacturer_profile_id)
            ->findOrFail((int) $data['product_id']);

        $result = $this->engine->preview(
            $discountSet,
            $distributor,
            $product,
            (int) $data['quantity'],
            isset($data['region_id']) ? (int) $data['region_id'] : null,
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

    private function requireProfile(Request $request): ManufacturerProfile
    {
        $profile = $this->context->profile($request->user());
        abort_if($profile === null, 403);

        return $profile;
    }

    private function assertOwns(Request $request, ManufacturerDiscountSet $discountSet): void
    {
        $profile = $this->requireProfile($request);
        abort_unless((int) $discountSet->manufacturer_profile_id === (int) $profile->id, 404);
    }
}

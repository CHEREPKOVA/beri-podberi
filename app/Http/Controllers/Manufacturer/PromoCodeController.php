<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturer\StoreManufacturerPromoCodeRequest;
use App\Http\Requests\Manufacturer\UpdateManufacturerPromoCodeRequest;
use App\Models\ManufacturerProfile;
use App\Models\ManufacturerPromoCode;
use App\Services\Discount\ManufacturerDiscountSetService;
use App\Services\Discount\ManufacturerPromoCodeService;
use App\Services\ManufacturerCompanyContext;
use App\Services\ManufacturerStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    public function __construct(
        private readonly ManufacturerCompanyContext $context,
        private readonly ManufacturerStaffAccess $access,
        private readonly ManufacturerPromoCodeService $promos,
        private readonly ManufacturerDiscountSetService $sets,
    ) {}

    public function index(Request $request): View
    {
        $profile = $this->requireProfile($request);
        $data = $this->promos->paginate($profile, $request);

        return view('manufacturer.promo-codes.index', [
            'codes' => $data['codes'],
            'types' => config('manufacturer_discounts.promo_discount_types'),
            'statuses' => config('manufacturer_discounts.promo_statuses'),
            'canManage' => $this->access->canAccessSection($request->user(), 'discounts.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $profile = $this->requireProfile($request);

        return view('manufacturer.promo-codes.edit', array_merge(
            $this->sets->formCatalog($profile),
            [
                'promo' => null,
                'generatedCode' => $this->promos->generateCode(),
                'types' => config('manufacturer_discounts.promo_discount_types'),
                'canManage' => true,
            ]
        ));
    }

    public function store(StoreManufacturerPromoCodeRequest $request): RedirectResponse
    {
        $profile = $this->requireProfile($request);
        $promo = $this->promos->create($profile, $request->user(), $request->validated());

        return redirect()
            ->route('manufacturer.promo-codes.edit', $promo)
            ->with('success', 'Промокод сохранён.');
    }

    public function edit(Request $request, ManufacturerPromoCode $promoCode): View
    {
        $this->assertOwns($request, $promoCode);
        $profile = $this->requireProfile($request);

        return view('manufacturer.promo-codes.edit', array_merge(
            $this->sets->formCatalog($profile),
            [
                'promo' => $promoCode,
                'generatedCode' => $promoCode->code,
                'types' => config('manufacturer_discounts.promo_discount_types'),
                'canManage' => $this->access->canAccessSection($request->user(), 'discounts.manage'),
            ]
        ));
    }

    public function update(UpdateManufacturerPromoCodeRequest $request, ManufacturerPromoCode $promoCode): RedirectResponse
    {
        $this->assertOwns($request, $promoCode);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $this->promos->update($promoCode, $request->user(), $request->validated());

        return redirect()
            ->route('manufacturer.promo-codes.edit', $promoCode)
            ->with('success', 'Промокод обновлён.');
    }

    public function toggle(Request $request, ManufacturerPromoCode $promoCode): RedirectResponse
    {
        $this->assertOwns($request, $promoCode);
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);
        $this->promos->toggle($promoCode, $request->user());

        return back()->with('success', $promoCode->fresh()->status === ManufacturerPromoCode::STATUS_ACTIVE
            ? 'Промокод включён.'
            : 'Промокод отключён.');
    }

    public function generate(Request $request): JsonResponse
    {
        abort_unless($this->access->canAccessSection($request->user(), 'discounts.manage'), 403);

        return response()->json(['code' => $this->promos->generateCode()]);
    }

    public function destroy(Request $request, ManufacturerPromoCode $promoCode): RedirectResponse
    {
        $this->assertOwns($request, $promoCode);
        if ($promoCode->isLocked()) {
            return back()->with('error', 'Нельзя удалить промокод, который уже использовался. Отключите его.');
        }
        $promoCode->delete();

        return redirect()
            ->route('manufacturer.promo-codes.index')
            ->with('success', 'Промокод удалён.');
    }

    private function requireProfile(Request $request): ManufacturerProfile
    {
        $profile = $this->context->profile($request->user());
        abort_if($profile === null, 403);

        return $profile;
    }

    private function assertOwns(Request $request, ManufacturerPromoCode $promo): void
    {
        $profile = $this->requireProfile($request);
        abort_unless((int) $promo->manufacturer_profile_id === (int) $profile->id, 404);
    }
}

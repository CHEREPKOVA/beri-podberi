<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\Region;
use App\Services\ManufacturerPartnerCatalogService;
use App\Services\ManufacturerStaffPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerCatalogController extends Controller
{
    public function __construct(
        private readonly ManufacturerPartnerCatalogService $catalog,
        private readonly ManufacturerStaffPermissions $permissions,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $manufacturer = $user->getOrCreateManufacturerProfile();
        $catalogType = $this->catalog->resolveCatalogType((string) $request->input('type', ''));

        $filters = [
            'search' => $request->input('search'),
            'region_ids' => $request->input('region_ids', []),
            'category_ids' => $request->input('category_ids'),
            'categories_reset' => $request->boolean('categories_reset'),
        ];

        $sort = (string) $request->input('sort', 'name');
        $direction = (string) $request->input('direction', 'asc');

        $regions = Region::active()->orderBy('name')->get();
        $filterableCategories = $this->catalog->filterableCategories($manufacturer);
        $defaultCategoryIds = $this->catalog->manufacturerCategoryIdsForPartnerFilter($manufacturer);

        if ($catalogType === ManufacturerPartnerCatalogService::CATALOG_COMPANIES) {
            $items = $this->catalog->paginateCompanies($filters, $sort, $direction);

            return view('manufacturer.partners.index', [
                'catalogType' => $catalogType,
                'items' => $items,
                'regions' => $regions,
                'filterableCategories' => collect(),
                'defaultCategoryIds' => [],
                'filters' => $filters,
                'sort' => $sort,
                'direction' => $direction,
                'permissions' => $this->permissions,
            ]);
        }

        $items = $this->catalog->paginateDistributors($manufacturer, $filters, $sort, $direction);

        return view('manufacturer.partners.index', [
            'catalogType' => $catalogType,
            'items' => $items,
            'manufacturer' => $manufacturer,
            'regions' => $regions,
            'filterableCategories' => $filterableCategories,
            'defaultCategoryIds' => $defaultCategoryIds,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'permissions' => $this->permissions,
            'catalogService' => $this->catalog,
        ]);
    }

    public function showDistributor(Request $request, DistributorProfile $distributor): View
    {
        $user = $request->user();
        $manufacturer = $user->getOrCreateManufacturerProfile();

        $distributor->load(['regions', 'productCategories', 'contacts', 'user']);

        $isPartner = $this->catalog->isPartner($manufacturer, $distributor);
        $cooperationStatus = $this->catalog->cooperationStatus($manufacturer->id, $distributor->id);
        $partnership = ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $manufacturer->id)
            ->where('distributor_profile_id', $distributor->id)
            ->first();

        $exclusiveRegions = $this->catalog->exclusiveRegionsForPair($manufacturer, $distributor);
        $availableExclusiveRegions = $this->catalog->availableExclusiveRegions($manufacturer, $distributor);
        $history = $this->catalog->partnershipLogs($manufacturer, $distributor);

        $orders = $this->permissions->canViewOrders($user)
            ? $this->catalog->distributorOrders($distributor, $manufacturer)
            : null;

        return view('manufacturer.partners.show_distributor', [
            'distributor' => $distributor,
            'manufacturer' => $manufacturer,
            'isPartner' => $isPartner,
            'cooperationStatus' => $cooperationStatus,
            'cooperationLabel' => $this->catalog->detailCooperationLabel(
                $cooperationStatus,
                $partnership?->status === ManufacturerDistributorPartnership::STATUS_BLOCKED,
            ),
            'partnership' => $partnership,
            'exclusiveRegions' => $exclusiveRegions,
            'availableExclusiveRegions' => $availableExclusiveRegions,
            'history' => $history,
            'orders' => $orders,
            'permissions' => $this->permissions,
            'catalogService' => $this->catalog,
        ]);
    }

    public function showCompany(Request $request, EndCompanyProfile $company): View
    {
        $company->load(['contacts', 'deliveryAddresses.region', 'user']);

        $orders = $this->permissions->canViewOrders($request->user())
            ? $company->platformOrders()->orderByDesc('ordered_at')->paginate(10)
            : null;

        return view('manufacturer.partners.show_company', [
            'company' => $company,
            'orders' => $orders,
            'permissions' => $this->permissions,
        ]);
    }

    public function addDistributor(Request $request, DistributorProfile $distributor): RedirectResponse
    {
        $user = $request->user();
        $manufacturer = $user->getOrCreateManufacturerProfile();

        if ($this->catalog->isPartner($manufacturer, $distributor)) {
            return back()->with('info', 'Дистрибьютор уже в вашем списке.');
        }

        $this->catalog->addToMyNetwork($manufacturer, $distributor, $user);

        return back()->with('success', 'Дистрибьютор добавлен в «Мои дистрибьюторы».');
    }

    public function removeDistributor(Request $request, DistributorProfile $distributor): RedirectResponse
    {
        $user = $request->user();
        $manufacturer = $user->getOrCreateManufacturerProfile();

        $this->catalog->removeFromMyNetwork($manufacturer, $distributor, $user);

        return redirect()
            ->route('manufacturer.partners.index')
            ->with('success', 'Дистрибьютор удалён из вашего списка.');
    }

    public function assignExclusive(Request $request, DistributorProfile $distributor): RedirectResponse
    {
        $user = $request->user();
        $manufacturer = $user->getOrCreateManufacturerProfile();

        $validated = $request->validate([
            'region_ids' => 'required|array|min:1',
            'region_ids.*' => 'integer|exists:regions,id',
        ]);

        if (! $this->catalog->isPartner($manufacturer, $distributor)) {
            $this->catalog->addToMyNetwork($manufacturer, $distributor, $user);
        }

        $result = $this->catalog->assignExclusiveRegions(
            $manufacturer,
            $distributor,
            $validated['region_ids'],
            $user,
        );

        if ($result['assigned'] === 0 && ! empty($result['skipped'])) {
            return back()->with('error', implode(' ', array_unique($result['skipped'])));
        }

        $message = $result['assigned'] > 0
            ? "Эксклюзивность назначена в {$result['assigned']} регион(ах)."
            : 'Не удалось назначить эксклюзивность.';

        if (! empty($result['skipped'])) {
            $message .= ' '.implode(' ', array_unique($result['skipped']));
        }

        return back()->with($result['assigned'] > 0 ? 'success' : 'error', $message);
    }
}

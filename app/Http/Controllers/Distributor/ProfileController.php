<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\DistributorContact;
use App\Models\DistributorDocument;
use App\Models\DistributorWarehouse;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\TransportCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->getOrCreateDistributorProfile();
        $tab = $request->get('tab', 'company');

        $profile->load([
            'contacts',
            'regions',
            'productCategories',
            'deliveryMethods',
            'transportCompanies',
            'documents',
        ]);

        $regions = Region::active()->orderBy('name')->get();
        $deliveryMethods = DeliveryMethod::active()->orderBy('sort_order')->get();
        $transportCompanies = TransportCompany::active()->orderBy('name')->get();
        $federalDistricts = Region::federalDistricts();
        $productCategoryRoots = ProductCategory::query()
            ->active()
            ->roots()
            ->with(['children' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('distributor.profile.index', compact(
            'profile',
            'tab',
            'regions',
            'deliveryMethods',
            'transportCompanies',
            'federalDistricts',
            'productCategoryRoots',
        ));
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'short_name' => 'nullable|string|max:255',
            'legal_form' => 'required|in:ooo,ip,pao,ao,gos',
            'kpp' => 'nullable|string|max:9',
            'ogrn' => 'nullable|string|max:15',
            'legal_address' => 'nullable|string|max:500',
            'actual_address' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:255',
            'bik' => 'nullable|string|max:9',
            'checking_account' => 'nullable|string|max:20',
            'correspondent_account' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $lockedFields = $profile->locked_fields ?? [];
        foreach ($lockedFields as $field) {
            unset($validated[$field]);
        }

        if ($request->hasFile('logo')) {
            if ($profile->logo) {
                Storage::disk('public')->delete($profile->logo);
            }
            $validated['logo'] = $request->file('logo')->store('distributor/logos', 'public');
        }

        $profile->update($validated);

        return redirect()
            ->route('distributor.profile', ['tab' => 'company'])
            ->with('success', 'Изменения сохранены успешно');
    }

    public function updateIntegration(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'integration_csv_enabled' => 'sometimes|boolean',
            'integration_yml_enabled' => 'sometimes|boolean',
            'integration_import_1c_stocks' => 'sometimes|boolean',
            'integration_export_orders_1c' => 'sometimes|boolean',
            'integration_csv_feed_url' => 'nullable|string|max:2048',
            'integration_yml_feed_url' => 'nullable|string|max:2048',
            'integration_comment' => 'nullable|string|max:2000',
            'zero_stock_behavior' => 'required|in:hide,on_order',
        ]);

        $validated['integration_csv_enabled'] = $request->boolean('integration_csv_enabled');
        $validated['integration_yml_enabled'] = $request->boolean('integration_yml_enabled');
        $validated['integration_import_1c_stocks'] = $request->boolean('integration_import_1c_stocks');
        $validated['integration_export_orders_1c'] = $request->boolean('integration_export_orders_1c');

        $profile->update($validated);

        return redirect()
            ->route('distributor.profile', ['tab' => 'integration'])
            ->with('success', 'Настройки интеграции сохранены');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['distributor_profile_id'] = $profile->id;
        $validated['is_primary'] = $profile->contacts()->count() === 0;

        DistributorContact::create($validated);

        return redirect()
            ->route('distributor.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт добавлен');
    }

    public function updateContact(Request $request, DistributorContact $contact): RedirectResponse
    {
        $this->authorizeContact($request, $contact);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $contact->update($validated);

        return redirect()
            ->route('distributor.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт обновлён');
    }

    public function deleteContact(Request $request, DistributorContact $contact): RedirectResponse
    {
        $this->authorizeContact($request, $contact);

        if (! $contact->canBeDeleted()) {
            return redirect()
                ->route('distributor.profile', ['tab' => 'contacts'])
                ->with('error', 'Нельзя удалить основной контакт');
        }

        $contact->delete();

        return redirect()
            ->route('distributor.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт удалён');
    }

    public function updateRegions(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'regions' => 'nullable|array',
            'regions.*' => 'exists:regions,id',
            'primary_region' => 'nullable|exists:regions,id',
        ]);

        $regionIds = $validated['regions'] ?? [];
        $primaryRegionId = $validated['primary_region'] ?? null;

        $syncData = [];
        foreach ($regionIds as $regionId) {
            $syncData[$regionId] = [
                'is_primary' => (int) $regionId === (int) $primaryRegionId,
            ];
        }

        $profile->regions()->sync($syncData);

        return redirect()
            ->route('distributor.profile', ['tab' => 'regions'])
            ->with('success', 'Регионы присутствия обновлены');
    }

    public function updateProductCategories(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:product_categories,id',
        ], [
            'category_ids.required' => 'Выберите хотя бы один тип продукции.',
            'category_ids.min' => 'Выберите хотя бы один тип продукции.',
        ]);

        $allowedIds = ProductCategory::query()
            ->active()
            ->whereIn('id', $validated['category_ids'])
            ->pluck('id')
            ->all();

        $profile->productCategories()->sync($allowedIds);

        return redirect()
            ->route('distributor.profile', ['tab' => 'product_categories'])
            ->with('success', 'Типы продукции сохранены');
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'type' => 'required|in:main,regional,store',
            'responsible_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'working_hours' => 'nullable|string|max:255',
            'shipping_conditions' => 'nullable|string|max:255',
        ]);

        $validated['distributor_profile_id'] = $profile->id;
        DistributorWarehouse::create($validated);

        return redirect()
            ->route('distributor.warehouses.index')
            ->with('success', 'Склад добавлен');
    }

    public function updateWarehouse(Request $request, DistributorWarehouse $warehouse): RedirectResponse
    {
        $this->authorizeWarehouse($request, $warehouse);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'type' => 'required|in:main,regional,store',
            'responsible_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'working_hours' => 'nullable|string|max:255',
            'shipping_conditions' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $warehouse->update($validated);

        return redirect()
            ->route('distributor.warehouses.index')
            ->with('success', 'Склад обновлён');
    }

    public function deleteWarehouse(Request $request, DistributorWarehouse $warehouse): RedirectResponse
    {
        $this->authorizeWarehouse($request, $warehouse);

        // При появлении заказов/остатков дистрибьютора — добавить проверку связей
        $warehouse->delete();

        return redirect()
            ->route('distributor.warehouses.index')
            ->with('success', 'Склад удалён');
    }

    public function exportWarehouses(Request $request)
    {
        $profile = $request->user()->distributorProfile;
        $warehouses = $profile->warehouses()->with('region')->get();
        $exportFilename = 'Список складов '.now()->format('d.m.Y').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$exportFilename.'"; filename*=UTF-8\'\''.rawurlencode($exportFilename),
        ];

        $callback = function () use ($warehouses) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Название', 'Адрес', 'Регион', 'Тип', 'Ответственный', 'Телефон', 'График', 'Условия отгрузки', 'Примечание', 'Активен'], ';');

            foreach ($warehouses as $warehouse) {
                fputcsv($file, [
                    $warehouse->name,
                    $warehouse->address,
                    $warehouse->region?->name ?? '',
                    $warehouse->typeLabel(),
                    $warehouse->responsible_person ?? '',
                    $warehouse->phone ?? '',
                    $warehouse->working_hours ?? '',
                    $warehouse->shipping_conditions ?? '',
                    $warehouse->notes ?? '',
                    $warehouse->is_active ? 'Да' : 'Нет',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function updateDelivery(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'delivery_methods' => 'nullable|array',
            'delivery_methods.*' => 'exists:delivery_methods,id',
            'transport_companies' => 'nullable|array',
            'transport_companies.*' => 'exists:transport_companies,id',
            'delivery_notes' => 'nullable|string|max:1000',
        ]);

        $profile->deliveryMethods()->sync($validated['delivery_methods'] ?? []);
        $profile->transportCompanies()->sync($validated['transport_companies'] ?? []);
        $profile->update(['delivery_notes' => $validated['delivery_notes'] ?? null]);

        return redirect()
            ->route('distributor.profile', ['tab' => 'delivery'])
            ->with('success', 'Настройки доставки сохранены');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $profile = $request->user()->distributorProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:registration_certificate,company_card,license,distribution_agreement,other',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store('distributor/documents', 'public');

        DistributorDocument::create([
            'distributor_profile_id' => $profile->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'valid_until' => $validated['valid_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('distributor.profile', ['tab' => 'documents'])
            ->with('success', 'Документ загружен');
    }

    public function deleteDocument(Request $request, DistributorDocument $document): RedirectResponse
    {
        $this->authorizeDocument($request, $document);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return redirect()
            ->route('distributor.profile', ['tab' => 'documents'])
            ->with('success', 'Документ удалён');
    }

    private function authorizeContact(Request $request, DistributorContact $contact): void
    {
        if ($contact->distributor_profile_id !== $request->user()->distributorProfile?->id) {
            abort(403);
        }
    }

    private function authorizeWarehouse(Request $request, DistributorWarehouse $warehouse): void
    {
        if ($warehouse->distributor_profile_id !== $request->user()->distributorProfile?->id) {
            abort(403);
        }
    }

    private function authorizeDocument(Request $request, DistributorDocument $document): void
    {
        if ($document->distributor_profile_id !== $request->user()->distributorProfile?->id) {
            abort(403);
        }
    }
}

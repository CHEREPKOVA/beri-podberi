<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\ManufacturerContact;
use App\Models\ManufacturerDocument;
use App\Models\ManufacturerProfile;
use App\Models\Region;
use App\Models\TransportCompany;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->getOrCreateManufacturerProfile();
        $tab = $request->get('tab', 'company');

        $profile->load([
            'contacts',
            'regions',
            'deliveryMethods',
            'transportCompanies',
            'documents',
        ]);

        $regions = Region::active()->orderBy('sort_order')->get();
        $deliveryMethods = DeliveryMethod::active()->orderBy('sort_order')->get();
        $transportCompanies = TransportCompany::active()->orderBy('sort_order')->get();
        $federalDistricts = Region::federalDistricts();

        return view('manufacturer.profile.index', compact(
            'profile',
            'tab',
            'regions',
            'deliveryMethods',
            'transportCompanies',
            'federalDistricts'
        ));
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'legal_form' => 'required|in:ooo,ip,pao,ao,gos',
            'inn' => 'required|string|max:12',
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
            $validated['logo'] = $request->file('logo')->store('manufacturer/logos', 'public');
        }

        $profile->update($validated);

        return redirect()
            ->route('manufacturer.profile', ['tab' => 'company'])
            ->with('success', 'Изменения сохранены успешно');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['manufacturer_profile_id'] = $profile->id;
        $validated['is_primary'] = $profile->contacts()->count() === 0;

        ManufacturerContact::create($validated);

        return redirect()
            ->route('manufacturer.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт добавлен');
    }

    public function updateContact(Request $request, ManufacturerContact $contact): RedirectResponse
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
            ->route('manufacturer.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт обновлён');
    }

    public function deleteContact(Request $request, ManufacturerContact $contact): RedirectResponse
    {
        $this->authorizeContact($request, $contact);

        if (!$contact->canBeDeleted()) {
            return redirect()
                ->route('manufacturer.profile', ['tab' => 'contacts'])
                ->with('error', 'Нельзя удалить основной контакт');
        }

        $contact->delete();

        return redirect()
            ->route('manufacturer.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт удалён');
    }

    public function updateRegions(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

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
                'is_primary' => $regionId == $primaryRegionId,
            ];
        }

        $profile->regions()->sync($syncData);

        return redirect()
            ->route('manufacturer.profile', ['tab' => 'regions'])
            ->with('success', 'Регионы присутствия обновлены');
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'type' => 'required|in:main,temporary,transit',
            'responsible_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'working_hours' => 'nullable|string|max:255',
        ]);

        $validated['manufacturer_profile_id'] = $profile->id;

        Warehouse::create($validated);

        return redirect()
            ->route('manufacturer.warehouses.index')
            ->with('success', 'Склад добавлен');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizeWarehouse($request, $warehouse);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'type' => 'required|in:main,temporary,transit',
            'responsible_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'working_hours' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $warehouse->update($validated);

        return redirect()
            ->route('manufacturer.warehouses.index')
            ->with('success', 'Склад обновлён');
    }

    public function deleteWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizeWarehouse($request, $warehouse);

        // TODO: Проверка на связанные активные заказы
        $warehouse->delete();

        return redirect()
            ->route('manufacturer.warehouses.index')
            ->with('success', 'Склад удалён');
    }

    public function exportWarehouses(Request $request)
    {
        $profile = $request->user()->manufacturerProfile;
        $warehouses = $profile->warehouses()->with('region')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="warehouses.csv"',
        ];

        $callback = function () use ($warehouses) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['Название', 'Адрес', 'Регион', 'Тип', 'Ответственный', 'Телефон', 'График работы', 'Примечание', 'В каталоге'], ';');

            foreach ($warehouses as $warehouse) {
                fputcsv($file, [
                    $warehouse->name,
                    $warehouse->address,
                    $warehouse->region?->name ?? '',
                    $warehouse->typeLabel(),
                    $warehouse->responsible_person ?? '',
                    $warehouse->phone ?? '',
                    $warehouse->working_hours ?? '',
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
        $profile = $request->user()->manufacturerProfile;

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
            ->route('manufacturer.profile', ['tab' => 'delivery'])
            ->with('success', 'Настройки доставки сохранены');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $profile = $request->user()->manufacturerProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:registration_certificate,company_card,license,product_certificate,distribution_agreement,other',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store('manufacturer/documents', 'public');

        ManufacturerDocument::create([
            'manufacturer_profile_id' => $profile->id,
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
            ->route('manufacturer.profile', ['tab' => 'documents'])
            ->with('success', 'Документ загружен');
    }

    public function deleteDocument(Request $request, ManufacturerDocument $document): RedirectResponse
    {
        $this->authorizeDocument($request, $document);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return redirect()
            ->route('manufacturer.profile', ['tab' => 'documents'])
            ->with('success', 'Документ удалён');
    }

    private function authorizeContact(Request $request, ManufacturerContact $contact): void
    {
        if ($contact->manufacturer_profile_id !== $request->user()->manufacturerProfile?->id) {
            abort(403);
        }
    }

    private function authorizeWarehouse(Request $request, Warehouse $warehouse): void
    {
        if ($warehouse->manufacturer_profile_id !== $request->user()->manufacturerProfile?->id) {
            abort(403);
        }
    }

    private function authorizeDocument(Request $request, ManufacturerDocument $document): void
    {
        if ($document->manufacturer_profile_id !== $request->user()->manufacturerProfile?->id) {
            abort(403);
        }
    }
}

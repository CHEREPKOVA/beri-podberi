<?php

namespace App\Http\Controllers\EndCompany;

use App\Http\Controllers\Controller;
use App\Models\EndCompanyContact;
use App\Models\EndCompanyDeliveryAddress;
use App\Models\EndCompanyDocument;
use App\Models\EndCompanyProfileChange;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->getOrCreateEndCompanyProfile();
        $tab = $request->get('tab', 'general');

        $profile->load(['contacts', 'documents', 'deliveryAddresses.region']);
        $regions = Region::active()->orderBy('name')->get();
        $changes = $profile->profileChanges()->with('user')->limit(50)->get();

        return view('end_company.profile.index', compact('profile', 'tab', 'regions', 'changes'));
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $profile = $request->user()->endCompanyProfile;

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'legal_form' => 'required|in:ooo,ip,pao,ao,gos',
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
            $validated['logo'] = $request->file('logo')->store('end_company/logos', 'public');
        }

        $profile->update($validated);
        $this->logChange($profile, $request->user()->id, 'general', 'Обновлена общая информация о компании');

        return redirect()
            ->route('end_company.profile', ['tab' => 'general'])
            ->with('success', 'Изменения сохранены');
    }

    public function updateLegal(Request $request): RedirectResponse
    {
        $profile = $request->user()->endCompanyProfile;

        $validated = $request->validate([
            'inn' => 'nullable|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'ogrn' => 'nullable|string|max:15',
            'legal_address' => 'nullable|string|max:500',
            'actual_address' => 'nullable|string|max:500',
            'director_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bik' => 'nullable|string|max:9',
            'checking_account' => 'nullable|string|max:20',
            'correspondent_account' => 'nullable|string|max:20',
        ]);

        $lockedFields = $profile->locked_fields ?? [];
        foreach ($lockedFields as $field) {
            unset($validated[$field]);
        }

        $profile->update($validated);
        $this->logChange($profile, $request->user()->id, 'legal', 'Обновлены юридические реквизиты');

        return redirect()
            ->route('end_company.profile', ['tab' => 'legal'])
            ->with('success', 'Реквизиты сохранены');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $profile = $request->user()->endCompanyProfile;

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['end_company_profile_id'] = $profile->id;
        $validated['is_primary'] = $profile->contacts()->count() === 0;

        EndCompanyContact::create($validated);
        $this->logChange($profile, $request->user()->id, 'contacts', 'Добавлен контакт');

        return redirect()
            ->route('end_company.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт добавлен');
    }

    public function updateContact(Request $request, EndCompanyContact $contact): RedirectResponse
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
        $this->logChange($contact->profile, $request->user()->id, 'contacts', 'Изменён контакт: '.$contact->full_name);

        return redirect()
            ->route('end_company.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт обновлён');
    }

    public function deleteContact(Request $request, EndCompanyContact $contact): RedirectResponse
    {
        $this->authorizeContact($request, $contact);

        if (! $contact->canBeDeleted()) {
            return redirect()
                ->route('end_company.profile', ['tab' => 'contacts'])
                ->with('error', 'Нельзя удалить основной контакт');
        }

        $name = $contact->full_name;
        $profile = $contact->profile;
        $contact->delete();
        $this->logChange($profile, $request->user()->id, 'contacts', 'Удалён контакт: '.$name);

        return redirect()
            ->route('end_company.profile', ['tab' => 'contacts'])
            ->with('success', 'Контакт удалён');
    }

    public function storeDeliveryAddress(Request $request): RedirectResponse
    {
        $profile = $request->user()->endCompanyProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'working_hours' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        $validated['end_company_profile_id'] = $profile->id;
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            $profile->deliveryAddresses()->update(['is_default' => false]);
        }

        EndCompanyDeliveryAddress::create($validated);
        $this->logChange($profile, $request->user()->id, 'delivery', 'Добавлен адрес доставки');

        return redirect()
            ->route('end_company.profile', ['tab' => 'delivery'])
            ->with('success', 'Адрес добавлен');
    }

    public function updateDeliveryAddress(Request $request, EndCompanyDeliveryAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'region_id' => 'nullable|exists:regions,id',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'working_hours' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            $address->profile->deliveryAddresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);
        $this->logChange($address->profile, $request->user()->id, 'delivery', 'Изменён адрес доставки: '.$address->name);

        return redirect()
            ->route('end_company.profile', ['tab' => 'delivery'])
            ->with('success', 'Адрес обновлён');
    }

    public function deleteDeliveryAddress(Request $request, EndCompanyDeliveryAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $name = $address->name;
        $profile = $address->profile;
        $address->delete();
        $this->logChange($profile, $request->user()->id, 'delivery', 'Удалён адрес доставки: '.$name);

        return redirect()
            ->route('end_company.profile', ['tab' => 'delivery'])
            ->with('success', 'Адрес удалён');
    }

    public function setDefaultDeliveryAddress(Request $request, EndCompanyDeliveryAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $profile = $address->profile;
        $profile->deliveryAddresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        $this->logChange($profile, $request->user()->id, 'delivery', 'Адрес по умолчанию: '.$address->name);

        return redirect()
            ->route('end_company.profile', ['tab' => 'delivery'])
            ->with('success', 'Адрес по умолчанию обновлён');
    }

    public function updateIntegration(Request $request): RedirectResponse
    {
        $profile = $request->user()->endCompanyProfile;

        $validated = $request->validate([
            'integration_webhook_url' => 'nullable|string|max:2048',
            'integration_comment' => 'nullable|string|max:2000',
        ]);

        $validated['integration_edi_enabled'] = $request->boolean('integration_edi_enabled');

        $profile->update($validated);
        $this->logChange($profile, $request->user()->id, 'integration', 'Обновлены настройки интеграции');

        return redirect()
            ->route('end_company.profile', ['tab' => 'integration'])
            ->with('success', 'Настройки интеграции сохранены');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $profile = $request->user()->endCompanyProfile;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:charter,company_card,power_of_attorney,requisites_pdf,contract,other',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store('end_company/documents', 'public');

        EndCompanyDocument::create([
            'end_company_profile_id' => $profile->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'valid_until' => $validated['valid_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->logChange($profile, $request->user()->id, 'documents', 'Загружен документ: '.$validated['name']);

        return redirect()
            ->route('end_company.profile', ['tab' => 'documents'])
            ->with('success', 'Документ загружен');
    }

    public function deleteDocument(Request $request, EndCompanyDocument $document): RedirectResponse
    {
        $this->authorizeDocument($request, $document);

        $name = $document->name;
        $profile = $document->profile;

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        $this->logChange($profile, $request->user()->id, 'documents', 'Удалён документ: '.$name);

        return redirect()
            ->route('end_company.profile', ['tab' => 'documents'])
            ->with('success', 'Документ удалён');
    }

    private function logChange(\App\Models\EndCompanyProfile $profile, int $userId, string $section, string $summary): void
    {
        EndCompanyProfileChange::create([
            'end_company_profile_id' => $profile->id,
            'user_id' => $userId,
            'section' => $section,
            'summary' => mb_substr($summary, 0, 500),
        ]);
    }

    private function authorizeContact(Request $request, EndCompanyContact $contact): void
    {
        if ($contact->end_company_profile_id !== $request->user()->endCompanyProfile?->id) {
            abort(403);
        }
    }

    private function authorizeAddress(Request $request, EndCompanyDeliveryAddress $address): void
    {
        if ($address->end_company_profile_id !== $request->user()->endCompanyProfile?->id) {
            abort(403);
        }
    }

    private function authorizeDocument(Request $request, EndCompanyDocument $document): void
    {
        if ($document->end_company_profile_id !== $request->user()->endCompanyProfile?->id) {
            abort(403);
        }
    }
}

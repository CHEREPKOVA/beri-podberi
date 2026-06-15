<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\ManufacturerProfile;
use App\Models\Region;
use App\Models\Role;
use App\Models\TransportCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const COMPANY_STATUSES = ['active', 'pending', 'blocked'];

    public function index(Request $request): View
    {
        $rows = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('users', 'users.id', '=', 'role_user.user_id')
            ->whereIn('roles.slug', $this->corporateTypes())
            ->whereNotNull('role_user.company_name')
            ->where('role_user.company_name', '!=', '')
            ->selectRaw('
                role_user.company_name as name,
                COALESCE(MAX(role_user.company_type), MAX(roles.slug)) as type,
                GROUP_CONCAT(DISTINCT roles.slug ORDER BY roles.sort_order SEPARATOR ",") as types_csv,
                COUNT(DISTINCT roles.slug) as types_count,
                COALESCE(MAX(role_user.company_status), "active") as status,
                MAX(role_user.company_region) as region,
                MAX(role_user.company_legal_name) as legal_name,
                MAX(role_user.company_contact_email) as contact_email,
                MAX(role_user.company_contact_phone) as contact_phone,
                COUNT(DISTINCT role_user.user_id) as users_count,
                SUM(CASE WHEN users.is_active = 1 THEN 1 ELSE 0 END) as active_users_count
            ')
            ->groupBy('role_user.company_name');

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $rows->havingRaw('name LIKE ?', ["%{$search}%"]);
        }

        if ($request->filled('type') && in_array($request->string('type')->toString(), $this->corporateTypes(), true)) {
            $rows->where('roles.slug', '=', $request->string('type')->toString());
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), self::COMPANY_STATUSES, true)) {
            $rows->having('status', '=', $request->string('status')->toString());
        }

        $selectedRegionIds = $this->normalizeRegionIds($request->input('region_ids', []));
        if ($selectedRegionIds !== []) {
            $regionNames = Region::query()
                ->whereIn('id', $selectedRegionIds)
                ->pluck('name')
                ->all();
            $matchingCompanyNames = $this->companyNamesMatchingRegionIds($selectedRegionIds);
            $rows->where(function ($query) use ($regionNames, $matchingCompanyNames): void {
                $applied = false;
                if ($regionNames !== []) {
                    $query->whereIn('role_user.company_region', $regionNames);
                    $applied = true;
                }
                if ($matchingCompanyNames !== []) {
                    if ($applied) {
                        $query->orWhereIn('role_user.company_name', $matchingCompanyNames);
                    } else {
                        $query->whereIn('role_user.company_name', $matchingCompanyNames);
                    }
                }
            });
        }

        $companies = DB::query()
            ->fromSub($rows, 'companies')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $regionCounts = $this->resolveCompanyRegionCountsForList(
            $companies->getCollection()->pluck('name')->all()
        );
        $companies->getCollection()->transform(function (object $company) use ($regionCounts): object {
            $company->regions_count = $regionCounts[$company->name] ?? 0;

            return $company;
        });

        $companyTypes = Role::whereIn('slug', $this->corporateTypes())->orderBy('sort_order')->get();
        $regions = Region::active()->orderBy('name')->get();

        return view('admin.companies.index', compact('companies', 'companyTypes', 'regions', 'selectedRegionIds'));
    }

    public function create(): View
    {
        $companyTypes = Role::whereIn('slug', $this->corporateTypes())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.companies.create', compact('companyTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_types' => ['required', 'array', 'min:1'],
            'company_types.*' => ['required', 'string', Rule::in($this->corporateTypes())],
            'full_name' => 'required|string|max:255',
            'inn' => ['required', 'string', 'regex:/^\d{10,12}$/'],
            'email' => 'required|string|email|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ], [], [
            'company_types' => 'Типы компании',
            'company_types.*' => 'Тип компании',
            'full_name' => 'Полное наименование',
            'inn' => 'ИНН',
            'email' => 'Email для входа',
            'password' => 'Пароль',
        ]);

        $selectedTypes = $this->normalizeSelectedCompanyTypes($validated['company_types']);
        abort_if($selectedTypes === [], 422, 'Выберите хотя бы один тип компании.');
        $primaryCompanyType = $selectedTypes[0];
        $rolesBySlug = Role::query()
            ->whereIn('slug', $selectedTypes)
            ->get()
            ->keyBy('slug');
        abort_if($rolesBySlug->count() !== count($selectedTypes), 500, 'Одна или несколько ролей компании не настроены.');

        $companyName = trim($validated['full_name']);
        $inn = $validated['inn'];
        $email = trim($validated['email']);
        $existingUser = User::where('email', $email)->first();

        if (! $existingUser && empty($validated['password'])) {
            return back()
                ->withInput()
                ->withErrors(['password' => 'Укажите пароль для нового пользователя.']);
        }

        $exists = DB::table('role_user')
            ->where('company_name', $companyName)
            ->whereIn('company_type', $this->corporateTypes())
            ->exists();
        if ($exists) {
            return back()
                ->withInput()
                ->withErrors(['full_name' => 'Компания с таким наименованием уже существует.']);
        }

        $user = $existingUser ?: User::create([
            'name' => $companyName,
            'email' => $email,
            'password' => Hash::make((string) $validated['password']),
            'is_active' => true,
        ]);

        $attachPayload = [];
        foreach ($selectedTypes as $companyType) {
            /** @var Role|null $role */
            $role = $rolesBySlug->get($companyType);
            if (! $role) {
                continue;
            }

            $alreadyAssigned = DB::table('role_user')
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->where('company_name', $companyName)
                ->exists();
            if ($alreadyAssigned) {
                continue;
            }

            $attachPayload[$role->id] = [
                'company_name' => $companyName,
                'company_type' => $companyType,
                'company_status' => 'active',
                'company_legal_name' => $companyName,
            ];
        }

        if ($attachPayload !== []) {
            $user->roles()->attach($attachPayload);
        }

        if (in_array(Role::SLUG_MANUFACTURER, $selectedTypes, true) && (! $existingUser || ! $user->manufacturerProfile()->exists())) {
            ManufacturerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $companyName,
                    'inn' => $inn,
                ]
            );
        }

        $companyKey = $this->encodeCompanyKey($primaryCompanyType, $companyName);

        return redirect()
            ->route('admin.companies.show', $companyKey)
            ->with('success', $existingUser
                ? 'Компания создана и привязана к существующему пользователю.'
                : 'Компания создана. Доступ для входа в личный кабинет добавлен.');
    }

    public function show(Request $request, string $companyKey): View
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $tab = $request->string('tab', 'company')->toString();
        $companyRoleSlugs = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.company_name', '=', $companyName)
            ->whereIn('roles.slug', $this->corporateTypes())
            ->orderBy('roles.sort_order')
            ->distinct()
            ->pluck('roles.slug')
            ->all();

        $company = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereNotNull('role_user.company_name')
            ->where('role_user.company_name', '=', $companyName)
            ->where('roles.slug', '=', $companyType)
            ->selectRaw('
                role_user.company_name as name,
                COALESCE(MAX(role_user.company_type), MAX(roles.slug)) as type,
                COALESCE(MAX(role_user.company_status), "active") as status,
                MAX(role_user.company_region) as region,
                MAX(role_user.company_legal_name) as legal_name,
                MAX(role_user.company_contact_email) as contact_email,
                MAX(role_user.company_contact_phone) as contact_phone,
                MAX(role_user.company_params) as params
            ')
            ->groupBy('role_user.company_name')
            ->first();

        abort_if(! $company, 404, 'Компания не найдена.');

        $employees = User::query()
            ->whereHas('roles', function (EloquentBuilder $q) use ($companyName, $companyType): void {
                $q->where('role_user.company_name', $companyName)
                    ->where(function (EloquentBuilder $inner) use ($companyType): void {
                        $inner->where('roles.slug', $companyType)
                            ->orWhere('roles.slug', Role::SLUG_COMPANY_EMPLOYEE);
                    });
            })
            ->with(['roles' => function ($q) use ($companyName): void {
                $q->where('role_user.company_name', $companyName);
            }])
            ->orderBy('name')
            ->get();

        $activity = DB::table('admin_action_logs')
            ->join('users', 'users.id', '=', 'admin_action_logs.admin_id')
            ->where('admin_action_logs.company_name', $companyName)
            ->where('admin_action_logs.company_type', $companyType)
            ->orderByDesc('admin_action_logs.id')
            ->select([
                'admin_action_logs.*',
                'users.name as admin_name',
            ])
            ->paginate(20)
            ->withQueryString();

        $roleOptions = Role::whereIn('slug', [$companyType, Role::SLUG_COMPANY_EMPLOYEE])->orderBy('sort_order')->get();
        $companyRoleKeys = [];
        foreach ($companyRoleSlugs as $roleSlug) {
            $companyRoleKeys[$roleSlug] = $this->encodeCompanyKey($roleSlug, $companyName);
        }
        $statusOptions = self::COMPANY_STATUSES;
        $companyKey = $this->encodeCompanyKey($companyType, $companyName);
        $companyProfile = null;

        if ($companyType === Role::SLUG_MANUFACTURER) {
            $owner = User::query()
                ->whereHas('roles', fn (EloquentBuilder $q) => $q->where('roles.slug', $companyType)->where('role_user.company_name', $companyName))
                ->with(['manufacturerProfile.contacts', 'manufacturerProfile.regions', 'manufacturerProfile.deliveryMethods', 'manufacturerProfile.transportCompanies', 'manufacturerProfile.documents', 'manufacturerProfile.warehouses.region'])
                ->first();
            $companyProfile = $owner?->manufacturerProfile;
        }

        $deliveryMethods = DeliveryMethod::active()->orderBy('sort_order')->get();
        $transportCompanies = TransportCompany::active()->orderBy('name')->get();

        return view('admin.companies.show', compact('company', 'companyKey', 'tab', 'companyProfile', 'employees', 'roleOptions', 'statusOptions', 'activity', 'deliveryMethods', 'transportCompanies', 'companyRoleSlugs', 'companyRoleKeys'));
    }

    public function updateCompany(Request $request, string $companyKey): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);

        $manufacturerProfile = null;
        if ($companyType === Role::SLUG_MANUFACTURER) {
            $manufacturerProfile = User::query()
                ->whereHas('roles', fn (EloquentBuilder $q) => $q
                    ->where('roles.slug', Role::SLUG_MANUFACTURER)
                    ->where('role_user.company_name', $companyName))
                ->with('manufacturerProfile')
                ->first()
                ?->manufacturerProfile;
        }

        $rules = [
            'status' => 'required|in:active,pending,blocked',
            'region' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'service_params' => 'nullable|string|max:2000',
        ];

        if ($manufacturerProfile) {
            $rules['manufacturer_full_name'] = 'required|string|max:255';
            $rules['manufacturer_inn'] = 'required|string|max:12';
        }

        $validated = $request->validate($rules);

        $updates = [
            'company_type' => $companyType,
            'company_status' => $validated['status'],
            'company_region' => $validated['region'] ?? null,
            'company_legal_name' => $validated['legal_name'] ?? null,
            'company_contact_email' => $validated['contact_email'] ?? null,
            'company_contact_phone' => $validated['contact_phone'] ?? null,
            'company_params' => ! empty($validated['service_params']) ? json_encode([
                'service_params' => $validated['service_params'],
            ], JSON_UNESCAPED_UNICODE) : null,
        ];

        $this->companyMembersPivotQuery($companyName, $companyType)->update($updates);

        if ($manufacturerProfile) {
            $manufacturerProfile->update([
                'full_name' => $validated['manufacturer_full_name'],
                'inn' => $validated['manufacturer_inn'],
            ]);
        }

        if ($validated['status'] === 'blocked') {
            User::query()
                ->whereHas('roles', fn (EloquentBuilder $q) => $q->where('role_user.company_name', $companyName))
                ->update(['is_active' => false]);
        }

        if ($validated['status'] === 'active') {
            User::query()
                ->whereHas('roles', fn (EloquentBuilder $q) => $q->where('role_user.company_name', $companyName))
                ->update(['is_active' => true]);
        }

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Данные компании обновлены.');
    }

    public function destroy(Request $request, string $companyKey): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);

        $users = User::query()
            ->whereHas('roles', fn (EloquentBuilder $q) => $q
                ->where('role_user.company_name', $companyName)
                ->whereIn('roles.slug', [$companyType, Role::SLUG_COMPANY_EMPLOYEE]))
            ->with(['roles' => fn ($q) => $q->wherePivot('company_name', $companyName)])
            ->get();

        DB::table('role_user')
            ->where('company_name', $companyName)
            ->whereIn('role_id', Role::whereIn('slug', [$companyType, Role::SLUG_COMPANY_EMPLOYEE])->pluck('id'))
            ->delete();

        foreach ($users as $user) {
            if ($user->roles()->count() === 0) {
                $user->delete();
            }
        }

        return redirect()->route('admin.companies.index')->with('success', 'Компания удалена.');
    }

    public function updateUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail((int) $validated['role_id']);
        abort_unless(in_array($role->slug, [$companyType, Role::SLUG_COMPANY_EMPLOYEE], true), 422, 'Недопустимая роль для пользователя компании.');

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('company_name', $companyName)
            ->whereIn('role_id', Role::whereIn('slug', [$companyType, Role::SLUG_COMPANY_EMPLOYEE])->pluck('id'))
            ->update(['role_id' => $role->id, 'company_type' => $companyType]);

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь обновлён.');
    }

    public function suspendUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $user->update(['is_active' => false]);
        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь заблокирован.');
    }

    public function activateUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $user->update(['is_active' => true]);
        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь разблокирован.');
    }

    public function resetPassword(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $newPassword = Str::random(12);
        $user->update(['password' => Hash::make($newPassword)]);

        return redirect()->route('admin.companies.show', $companyKey)
            ->with('success', "Пароль пользователя «{$user->name}» сброшен.")
            ->with('temporary_password', $newPassword);
    }

    public function deleteUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('company_name', $companyName)
            ->delete();

        if ($user->roles()->count() === 0) {
            $user->delete();
        }

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь удалён из компании.');
    }

    /**
     * @return array<string, int>
     */
    private function resolveCompanyRegionCountsForList(array $companyNames): array
    {
        $companyNames = array_values(array_unique(array_filter($companyNames)));
        if ($companyNames === []) {
            return [];
        }

        $regionIdsByCompany = collect();

        $regionIdsByCompany = $regionIdsByCompany->merge(
            DB::table('role_user as ru')
                ->join('manufacturer_profiles as mp', 'mp.user_id', '=', 'ru.user_id')
                ->join('manufacturer_region as mr', 'mr.manufacturer_profile_id', '=', 'mp.id')
                ->whereIn('ru.company_name', $companyNames)
                ->select('ru.company_name', 'mr.region_id')
                ->distinct()
                ->get()
        );

        $regionIdsByCompany = $regionIdsByCompany->merge(
            DB::table('role_user as ru')
                ->join('distributor_profiles as dp', 'dp.user_id', '=', 'ru.user_id')
                ->join('distributor_region as dr', 'dr.distributor_profile_id', '=', 'dp.id')
                ->whereIn('ru.company_name', $companyNames)
                ->select('ru.company_name', 'dr.region_id')
                ->distinct()
                ->get()
        );

        $regionIdsByCompany = $regionIdsByCompany->merge(
            DB::table('role_user as ru')
                ->join('end_company_profiles as ep', 'ep.user_id', '=', 'ru.user_id')
                ->join('end_company_delivery_addresses as ea', 'ea.end_company_profile_id', '=', 'ep.id')
                ->whereIn('ru.company_name', $companyNames)
                ->whereNotNull('ea.region_id')
                ->select('ru.company_name', 'ea.region_id')
                ->distinct()
                ->get()
        );

        return $regionIdsByCompany
            ->groupBy('company_name')
            ->map(fn ($group) => $group->pluck('region_id')->unique()->filter()->count())
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function normalizeRegionIds(mixed $regionIds): array
    {
        return collect($regionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $regionIds
     * @return array<int, string>
     */
    private function companyNamesMatchingRegionIds(array $regionIds): array
    {
        if ($regionIds === []) {
            return [];
        }

        $names = collect();

        $names = $names->merge(
            DB::table('role_user as ru')
                ->join('manufacturer_profiles as mp', 'mp.user_id', '=', 'ru.user_id')
                ->join('manufacturer_region as mr', 'mr.manufacturer_profile_id', '=', 'mp.id')
                ->whereIn('mr.region_id', $regionIds)
                ->distinct()
                ->pluck('ru.company_name')
        );

        $names = $names->merge(
            DB::table('role_user as ru')
                ->join('distributor_profiles as dp', 'dp.user_id', '=', 'ru.user_id')
                ->join('distributor_region as dr', 'dr.distributor_profile_id', '=', 'dp.id')
                ->whereIn('dr.region_id', $regionIds)
                ->distinct()
                ->pluck('ru.company_name')
        );

        $names = $names->merge(
            DB::table('role_user as ru')
                ->join('end_company_profiles as ep', 'ep.user_id', '=', 'ru.user_id')
                ->join('end_company_delivery_addresses as ea', 'ea.end_company_profile_id', '=', 'ep.id')
                ->whereIn('ea.region_id', $regionIds)
                ->distinct()
                ->pluck('ru.company_name')
        );

        return $names
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function companyMembersPivotQuery(string $companyName, string $companyType)
    {
        $roleIds = Role::whereIn('slug', [$companyType, Role::SLUG_COMPANY_EMPLOYEE])->pluck('id');

        return DB::table('role_user')
            ->where('company_name', $companyName)
            ->whereIn('role_id', $roleIds);
    }

    private function assertUserInCompany(User $user, string $companyName): void
    {
        $exists = $user->roles()
            ->wherePivot('company_name', $companyName)
            ->exists();

        abort_if(! $exists, 404, 'Пользователь не относится к данной компании.');
    }

    private function encodeCompanyKey(string $type, string $name): string
    {
        return rtrim(strtr(base64_encode($type.'|'.$name), '+/', '-_'), '=');
    }

    private function decodeCompanyKey(string $companyKey): array
    {
        $decoded = base64_decode(strtr($companyKey, '-_', '+/'), true);
        abort_if($decoded === false || ! str_contains($decoded, '|'), 404, 'Некорректный идентификатор компании.');

        [$type, $name] = explode('|', $decoded, 2);
        abort_unless(in_array($type, $this->corporateTypes(), true) && $name !== '', 404, 'Компания не найдена.');

        return [$type, $name];
    }

    /**
     * Корпоративные роли, которые можно создавать через раздел компаний.
     *
     * @return array<int, string>
     */
    private function corporateTypes(): array
    {
        return Role::corporateSlugsWithEmployees();
    }

    /**
     * @param  array<int, string>  $selectedTypes
     * @return array<int, string>
     */
    private function normalizeSelectedCompanyTypes(array $selectedTypes): array
    {
        $selectedLookup = array_fill_keys($selectedTypes, true);
        $normalized = [];

        foreach ($this->corporateTypes() as $corporateType) {
            if (isset($selectedLookup[$corporateType])) {
                $normalized[] = $corporateType;
            }
        }

        return $normalized;
    }

}

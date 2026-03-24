<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\Role;
use App\Models\TransportCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const CORPORATE_TYPES = [
        Role::SLUG_MANUFACTURER,
        Role::SLUG_DISTRIBUTOR,
        Role::SLUG_END_COMPANY,
    ];

    private const COMPANY_STATUSES = ['active', 'pending', 'blocked'];

    public function index(Request $request): View
    {
        $rows = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->leftJoin('users', 'users.id', '=', 'role_user.user_id')
            ->whereIn('roles.slug', self::CORPORATE_TYPES)
            ->whereNotNull('role_user.company_name')
            ->where('role_user.company_name', '!=', '')
            ->selectRaw('
                role_user.company_name as name,
                COALESCE(MAX(role_user.company_type), MAX(roles.slug)) as type,
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

        if ($request->filled('type') && in_array($request->string('type')->toString(), self::CORPORATE_TYPES, true)) {
            $rows->having('type', '=', $request->string('type')->toString());
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), self::COMPANY_STATUSES, true)) {
            $rows->having('status', '=', $request->string('status')->toString());
        }

        if ($request->filled('region')) {
            $rows->having('region', 'LIKE', '%' . $request->string('region')->toString() . '%');
        }

        $companies = DB::query()
            ->fromSub($rows, 'companies')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $companyTypes = Role::whereIn('slug', self::CORPORATE_TYPES)->orderBy('sort_order')->get();

        return view('admin.companies.index', compact('companies', 'companyTypes'));
    }

    public function show(Request $request, string $companyKey): View
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $tab = $request->string('tab', 'company')->toString();

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
            ->limit(30)
            ->get([
                'admin_action_logs.*',
                'users.name as admin_name',
            ]);

        $roleOptions = Role::whereIn('slug', [$companyType, Role::SLUG_COMPANY_EMPLOYEE])->orderBy('sort_order')->get();
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
        $transportCompanies = TransportCompany::active()->orderBy('sort_order')->get();

        return view('admin.companies.show', compact('company', 'companyKey', 'tab', 'companyProfile', 'employees', 'roleOptions', 'statusOptions', 'activity', 'deliveryMethods', 'transportCompanies'));
    }

    public function updateCompany(Request $request, string $companyKey): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);

        $validated = $request->validate([
            'status' => 'required|in:active,pending,blocked',
            'region' => 'nullable|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'service_params' => 'nullable|string|max:2000',
        ]);

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

        $this->logAction($request, 'company.updated', $companyName, $companyType, [
            'status' => $validated['status'],
            'region' => $validated['region'] ?? null,
            'legal_name' => $validated['legal_name'] ?? null,
        ]);

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Данные компании обновлены.');
    }

    public function updateUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
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

        $this->logAction($request, 'company.user.updated', $companyName, $companyType, [
            'user_id' => $user->id,
            'role' => $role->slug,
        ]);

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь обновлён.');
    }

    public function suspendUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $user->update(['is_active' => false]);
        $this->logAction($request, 'company.user.suspended', $companyName, $companyType, ['user_id' => $user->id]);

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь заблокирован.');
    }

    public function activateUser(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $user->update(['is_active' => true]);
        $this->logAction($request, 'company.user.activated', $companyName, $companyType, ['user_id' => $user->id]);

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь разблокирован.');
    }

    public function resetPassword(Request $request, string $companyKey, User $user): RedirectResponse
    {
        [$companyType, $companyName] = $this->decodeCompanyKey($companyKey);
        $this->assertUserInCompany($user, $companyName);

        $newPassword = Str::random(12);
        $user->update(['password' => Hash::make($newPassword)]);

        $this->logAction($request, 'company.user.password_reset', $companyName, $companyType, ['user_id' => $user->id]);

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

        $this->logAction($request, 'company.user.deleted', $companyName, $companyType, ['user_id' => $user->id]);

        return redirect()->route('admin.companies.show', $companyKey)->with('success', 'Пользователь удалён из компании.');
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
        return rtrim(strtr(base64_encode($type . '|' . $name), '+/', '-_'), '=');
    }

    private function decodeCompanyKey(string $companyKey): array
    {
        $decoded = base64_decode(strtr($companyKey, '-_', '+/'), true);
        abort_if($decoded === false || ! str_contains($decoded, '|'), 404, 'Некорректный идентификатор компании.');

        [$type, $name] = explode('|', $decoded, 2);
        abort_unless(in_array($type, self::CORPORATE_TYPES, true) && $name !== '', 404, 'Компания не найдена.');

        return [$type, $name];
    }

    private function logAction(Request $request, string $action, string $companyName, string $companyType, array $context = []): void
    {
        DB::table('admin_action_logs')->insert([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'target_type' => null,
            'target_id' => null,
            'company_name' => $companyName,
            'company_type' => $companyType,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

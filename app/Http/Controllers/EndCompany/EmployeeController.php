<?php

namespace App\Http\Controllers\EndCompany;

use App\Http\Controllers\Controller;
use App\Http\Requests\EndCompany\StoreEndCompanyEmployeeRequest;
use App\Http\Requests\EndCompany\UpdateEndCompanyEmployeeRequest;
use App\Models\EndCompanyEmployeeRole;
use App\Models\PlatformOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\EndCompanyCompanyContext;
use App\Services\EndCompanyEmployeeService;
use App\Services\EndCompanyStaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EndCompanyEmployeeService $employees,
        private readonly EndCompanyCompanyContext $context,
        private readonly EndCompanyStaffAccess $access,
    ) {}

    public function index(Request $request): View
    {
        $admin = $request->user();
        $profile = $admin->getOrCreateEndCompanyProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $employees = $this->employees->listForAdmin($admin);

        return view('end_company.employees.index', [
            'employees' => $employees,
            'roles' => $roles,
            'profile' => $profile,
            'tab' => $request->string('tab', 'employees')->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        $admin = $request->user();
        $profile = $admin->getOrCreateEndCompanyProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $addresses = $profile->deliveryAddresses()->with('region')->orderByDesc('is_default')->orderBy('name')->get();

        return view('end_company.employees.create', [
            'roles' => $roles,
            'addresses' => $addresses,
            'orderOptions' => config('end_company_staff.orders'),
            'permissions' => EndCompanyEmployeeRole::normalizePermissions([]),
        ]);
    }

    public function store(StoreEndCompanyEmployeeRequest $request): RedirectResponse
    {
        try {
            $this->employees->invite($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('end_company.employees.index')
            ->with('success', 'Приглашение отправлено. Сотрудник появится в списке со статусом «Ожидает активации».');
    }

    public function show(Request $request, User $employee): View
    {
        $admin = $request->user();
        $employee = $this->employees->requireOwnedEmployee($admin, $employee);
        $profile = $admin->getOrCreateEndCompanyProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $addresses = $profile->deliveryAddresses()->with('region')->orderByDesc('is_default')->orderBy('name')->get();
        $params = $this->context->employeeParams($employee);
        $status = $this->access->status($employee);
        $roleId = (int) ($params['employee_role_id'] ?? 0);
        $roleName = $roles === []
            ? 'Сотрудник'
            : (collect($roles)->firstWhere('id', $roleId)?->name ?? 'Сотрудник');

        $employeeRole = $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        $addedAt = $employeeRole?->pivot?->created_at ?? $employee->created_at;

        $recentOrders = PlatformOrder::query()
            ->where('end_company_profile_id', $profile->id)
            ->where('created_by_user_id', $employee->id)
            ->orderByDesc('ordered_at')
            ->limit(8)
            ->get(['id', 'order_number', 'status', 'ordered_at', 'total_amount']);

        return view('end_company.employees.show', [
            'employee' => $employee,
            'roles' => $roles,
            'addresses' => $addresses,
            'orderOptions' => config('end_company_staff.orders'),
            'permissions' => EndCompanyEmployeeRole::normalizePermissions(
                is_array($params['permissions'] ?? null) ? $params['permissions'] : []
            ),
            'addressIds' => array_map('intval', $params['delivery_address_ids'] ?? []),
            'allAddresses' => (bool) ($params['all_addresses'] ?? false),
            'phone' => (string) ($params['phone'] ?? ''),
            'position' => (string) ($params['position'] ?? ''),
            'comments' => (string) ($params['comments'] ?? ''),
            'status' => $status,
            'statusLabel' => $this->access->statusLabel($status),
            'roleName' => $roleName,
            'roleId' => $roleId,
            'addedAt' => $addedAt,
            'canDelete' => ! $this->employees->hasOperationalHistory($employee),
            'recentOrders' => $recentOrders,
        ]);
    }

    public function update(UpdateEndCompanyEmployeeRequest $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->update($request->user(), $employee, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('end_company.employees.show', $employee)
            ->with('success', 'Данные сотрудника сохранены.');
    }

    public function resetPassword(Request $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->resetPassword($request->user(), $employee);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ссылка для восстановления пароля отправлена на email сотрудника.');
    }

    public function resendInvitation(Request $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->resendInvitation($request->user(), $employee);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Приглашение повторно отправлено на email сотрудника.');
    }

    public function block(Request $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->block($request->user(), $employee, $request->boolean('notify', true));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Сотрудник заблокирован. Авторизация в систему невозможна.');
    }

    public function unblock(Request $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->unblock($request->user(), $employee, $request->boolean('notify', true));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Доступ сотрудника восстановлен.');
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->delete($request->user(), $employee);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('end_company.employees.index')
            ->with('success', 'Сотрудник удалён.');
    }
}

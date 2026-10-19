<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StoreDistributorEmployeeRequest;
use App\Http\Requests\Distributor\UpdateDistributorEmployeeRequest;
use App\Models\DistributorEmployeeRole;
use App\Models\Role;
use App\Models\User;
use App\Services\DistributorCompanyContext;
use App\Services\DistributorEmployeeService;
use App\Services\DistributorStaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly DistributorEmployeeService $employees,
        private readonly DistributorCompanyContext $context,
        private readonly DistributorStaffAccess $access,
    ) {}

    public function index(Request $request): View
    {
        $admin = $request->user();
        $profile = $admin->getOrCreateDistributorProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $employees = $this->employees->listForAdmin($admin);

        return view('distributor.employees.index', [
            'employees' => $employees,
            'roles' => $roles,
            'profile' => $profile,
            'tab' => $request->string('tab', 'employees')->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        $admin = $request->user();
        $profile = $admin->getOrCreateDistributorProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $warehouses = $profile->warehouses()->with('region')->orderBy('name')->get();

        return view('distributor.employees.create', [
            'roles' => $roles,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreDistributorEmployeeRequest $request): RedirectResponse
    {
        try {
            $this->employees->invite($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('distributor.employees.index')
            ->with('success', 'Приглашение отправлено. Сотрудник появится в списке со статусом «Ожидает активации».');
    }

    public function show(Request $request, User $employee): View
    {
        $admin = $request->user();
        $employee = $this->employees->requireOwnedEmployee($admin, $employee);
        $profile = $admin->getOrCreateDistributorProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $warehouses = $profile->warehouses()->with('region')->orderBy('name')->get();
        $params = $this->context->employeeParams($employee);
        $status = $this->access->status($employee);
        $roleId = (int) ($params['employee_role_id'] ?? 0);
        $assignedRole = collect($roles)->firstWhere('id', $roleId);
        $roleName = $assignedRole?->name ?? 'Сотрудник';

        $employeeRole = $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        $addedAt = $employeeRole?->pivot?->created_at ?? $employee->created_at;

        return view('distributor.employees.show', [
            'employee' => $employee,
            'roles' => $roles,
            'warehouses' => $warehouses,
            'orderOptions' => config('distributor_staff.orders'),
            'purchaseOptions' => config('distributor_staff.purchases'),
            'productOptions' => config('distributor_staff.products'),
            'permissions' => $assignedRole?->normalizedPermissions()
                ?? DistributorEmployeeRole::normalizePermissions(
                    is_array($params['permissions'] ?? null) ? $params['permissions'] : []
                ),
            'warehouseIds' => array_map('intval', $params['warehouse_ids'] ?? []),
            'allWarehouses' => (bool) ($params['all_warehouses'] ?? false),
            'phone' => (string) ($params['phone'] ?? ''),
            'position' => (string) ($params['position'] ?? ''),
            'status' => $status,
            'statusLabel' => $this->access->statusLabel($status),
            'roleName' => $roleName,
            'roleId' => $roleId,
            'addedAt' => $addedAt,
            'canDelete' => ! $this->employees->hasOperationalHistory($employee),
        ]);
    }

    public function update(UpdateDistributorEmployeeRequest $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->update($request->user(), $employee, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('distributor.employees.show', $employee)
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
            ->route('distributor.employees.index')
            ->with('success', 'Сотрудник удалён.');
    }
}

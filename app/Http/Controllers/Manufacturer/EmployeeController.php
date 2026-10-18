<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturer\StoreManufacturerEmployeeRequest;
use App\Http\Requests\Manufacturer\UpdateManufacturerEmployeeRequest;
use App\Models\ManufacturerEmployeeRole;
use App\Models\Role;
use App\Models\User;
use App\Services\ManufacturerCompanyContext;
use App\Services\ManufacturerEmployeeService;
use App\Services\ManufacturerStaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly ManufacturerEmployeeService $employees,
        private readonly ManufacturerCompanyContext $context,
        private readonly ManufacturerStaffAccess $access,
    ) {}

    public function index(Request $request): View
    {
        $admin = $request->user();
        $profile = $admin->getOrCreateManufacturerProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $employees = $this->employees->listForAdmin($admin);

        return view('manufacturer.employees.index', [
            'employees' => $employees,
            'roles' => $roles,
            'profile' => $profile,
            'tab' => $request->string('tab', 'employees')->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        $admin = $request->user();
        $profile = $admin->getOrCreateManufacturerProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $warehouses = $profile->warehouses()->with('region')->orderBy('name')->get();

        return view('manufacturer.employees.create', [
            'roles' => $roles,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreManufacturerEmployeeRequest $request): RedirectResponse
    {
        try {
            $this->employees->invite($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('manufacturer.employees.index')
            ->with('success', 'Приглашение отправлено на указанный email');
    }

    public function show(Request $request, User $employee): View
    {
        $admin = $request->user();
        $employee = $this->employees->requireOwnedEmployee($admin, $employee);
        $profile = $admin->getOrCreateManufacturerProfile();
        $roles = $this->employees->ensureDefaultRoles($profile);
        $warehouses = $profile->warehouses()->with('region')->orderBy('name')->get();
        $params = $this->context->employeeParams($employee);
        $status = $this->access->status($employee);
        $roleId = (int) ($params['employee_role_id'] ?? 0);
        $assignedRole = collect($roles)->firstWhere('id', $roleId);
        $roleName = $assignedRole?->name ?? 'Сотрудник';

        $employeeRole = $employee->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        $addedAt = $employeeRole?->pivot?->created_at ?? $employee->created_at;

        return view('manufacturer.employees.show', [
            'employee' => $employee,
            'roles' => $roles,
            'warehouses' => $warehouses,
            'orderOptions' => config('manufacturer_staff.orders'),
            'productOptions' => config('manufacturer_staff.products'),
            'discountOptions' => config('manufacturer_staff.discounts'),
            'permissions' => $assignedRole?->normalizedPermissions()
                ?? ManufacturerEmployeeRole::normalizePermissions(
                    is_array($params['permissions'] ?? null) ? $params['permissions'] : []
                ),
            'warehouseIds' => array_map('intval', $params['warehouse_ids'] ?? []),
            'allWarehouses' => (bool) ($params['all_warehouses'] ?? false),
            'status' => $status,
            'statusLabel' => $this->access->statusLabel($status),
            'roleName' => $roleName,
            'roleId' => $roleId,
            'addedAt' => $addedAt,
            'canDelete' => ! $this->employees->hasOperationalHistory($employee),
        ]);
    }

    public function update(UpdateManufacturerEmployeeRequest $request, User $employee): RedirectResponse
    {
        try {
            $this->employees->update($request->user(), $employee, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()
            ->route('manufacturer.employees.show', $employee)
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
            ->route('manufacturer.employees.index')
            ->with('success', 'Сотрудник удалён.');
    }
}

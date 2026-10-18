<?php

namespace App\Http\Controllers\EndCompany;

use App\Http\Controllers\Controller;
use App\Http\Requests\EndCompany\StoreEndCompanyEmployeeRoleRequest;
use App\Models\EndCompanyEmployeeRole;
use App\Services\EndCompanyEmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EmployeeRoleController extends Controller
{
    public function __construct(
        private readonly EndCompanyEmployeeService $employees,
    ) {}

    public function store(StoreEndCompanyEmployeeRoleRequest $request): RedirectResponse
    {
        $this->employees->createRole($request->user(), $request->validated());

        return redirect()
            ->route('end_company.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль создана.');
    }

    public function update(StoreEndCompanyEmployeeRoleRequest $request, EndCompanyEmployeeRole $role): RedirectResponse
    {
        try {
            $this->employees->updateRole($request->user(), $role, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('end_company.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль обновлена.');
    }

    public function destroy(Request $request, EndCompanyEmployeeRole $role): RedirectResponse
    {
        try {
            $this->employees->deleteRole($request->user(), $role);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('end_company.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль удалена.');
    }
}

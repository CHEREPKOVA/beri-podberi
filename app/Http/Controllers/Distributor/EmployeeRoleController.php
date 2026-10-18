<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributor\StoreDistributorEmployeeRoleRequest;
use App\Models\DistributorEmployeeRole;
use App\Services\DistributorEmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EmployeeRoleController extends Controller
{
    public function __construct(
        private readonly DistributorEmployeeService $employees,
    ) {}

    public function store(StoreDistributorEmployeeRoleRequest $request): RedirectResponse
    {
        $this->employees->createRole($request->user(), $request->validated());

        return redirect()
            ->route('distributor.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль создана.');
    }

    public function update(StoreDistributorEmployeeRoleRequest $request, DistributorEmployeeRole $role): RedirectResponse
    {
        try {
            $this->employees->updateRole($request->user(), $role, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('distributor.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль обновлена.');
    }

    public function destroy(Request $request, DistributorEmployeeRole $role): RedirectResponse
    {
        try {
            $this->employees->deleteRole($request->user(), $role);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('distributor.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль удалена.');
    }
}

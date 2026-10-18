<?php

namespace App\Http\Controllers\Manufacturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturer\StoreManufacturerEmployeeRoleRequest;
use App\Models\ManufacturerEmployeeRole;
use App\Services\ManufacturerEmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EmployeeRoleController extends Controller
{
    public function __construct(
        private readonly ManufacturerEmployeeService $employees,
    ) {}

    public function store(StoreManufacturerEmployeeRoleRequest $request): RedirectResponse
    {
        $this->employees->createRole($request->user(), $request->validated());

        return redirect()
            ->route('manufacturer.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль создана.');
    }

    public function update(StoreManufacturerEmployeeRoleRequest $request, ManufacturerEmployeeRole $role): RedirectResponse
    {
        try {
            $this->employees->updateRole($request->user(), $role, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('manufacturer.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль обновлена.');
    }

    public function destroy(Request $request, ManufacturerEmployeeRole $role): RedirectResponse
    {
        try {
            $this->employees->deleteRole($request->user(), $role);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('manufacturer.employees.index', ['tab' => 'roles'])
            ->with('success', 'Роль удалена.');
    }
}

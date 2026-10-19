<?php

namespace App\Http\Requests\Distributor;

use App\Services\DistributorStaffAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreDistributorEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return app(DistributorStaffAccess::class)->canManageEmployees($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'employee_role_id' => ['required', 'integer'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['integer'],
            'all_warehouses' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ФИО',
            'email' => 'Email',
            'phone' => 'Телефон',
            'position' => 'Должность',
            'employee_role_id' => 'Роль сотрудника',
            'warehouse_ids' => 'Филиалы',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'all_warehouses' => $this->boolean('all_warehouses'),
        ]);
    }
}

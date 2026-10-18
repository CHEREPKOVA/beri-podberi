<?php

namespace App\Http\Requests\Manufacturer;

use Illuminate\Foundation\Http\FormRequest;

class StoreManufacturerEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManufacturerCompanyAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
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

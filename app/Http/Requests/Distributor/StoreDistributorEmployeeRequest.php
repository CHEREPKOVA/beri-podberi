<?php

namespace App\Http\Requests\Distributor;

use App\Services\DistributorStaffAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $orderKeys = array_keys(config('distributor_staff.orders', []));
        $purchaseKeys = array_keys(config('distributor_staff.purchases', []));
        $productKeys = array_keys(config('distributor_staff.products', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'employee_role_id' => ['required', 'integer'],
            'permissions' => ['required', 'array'],
            'permissions.orders' => ['required', Rule::in($orderKeys)],
            'permissions.purchases' => ['required', Rule::in($purchaseKeys)],
            'permissions.products' => ['required', Rule::in($productKeys)],
            'permissions.prices' => ['nullable', 'boolean'],
            'permissions.warehouses' => ['nullable', 'boolean'],
            'permissions.employees' => ['nullable', 'boolean'],
            'permissions.discounts' => ['nullable', 'boolean'],
            'permissions.import' => ['nullable', 'boolean'],
            'permissions.manufacturer_chat' => ['nullable', 'boolean'],
            'permissions.company_profile' => ['nullable', 'boolean'],
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
            'permissions.orders' => 'Доступ к заказам',
            'permissions.purchases' => 'Доступ к покупкам',
            'permissions.products' => 'Доступ к номенклатуре',
            'warehouse_ids' => 'Филиалы',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => array_merge($this->input('permissions', []), [
                'prices' => $this->boolean('permissions.prices'),
                'warehouses' => $this->boolean('permissions.warehouses'),
                'employees' => $this->boolean('permissions.employees'),
                'discounts' => $this->boolean('permissions.discounts'),
                'import' => $this->boolean('permissions.import'),
                'manufacturer_chat' => $this->boolean('permissions.manufacturer_chat'),
                'company_profile' => $this->boolean('permissions.company_profile'),
            ]),
            'all_warehouses' => $this->boolean('all_warehouses'),
        ]);
    }
}

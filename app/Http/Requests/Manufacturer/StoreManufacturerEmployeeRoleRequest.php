<?php

namespace App\Http\Requests\Manufacturer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManufacturerEmployeeRoleRequest extends FormRequest
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
        $orderKeys = array_keys(config('manufacturer_staff.orders', []));
        $productKeys = array_keys(config('manufacturer_staff.products', []));
        $discountKeys = array_keys(config('manufacturer_staff.discounts', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.orders' => ['required', Rule::in($orderKeys)],
            'permissions.products' => ['required', Rule::in($productKeys)],
            'permissions.discounts' => ['nullable', Rule::in($discountKeys)],
            'permissions.warehouses' => ['nullable', 'boolean'],
            'permissions.company_profile' => ['nullable', 'boolean'],
            'permissions.claims' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Название роли',
            'permissions.orders' => 'Доступ к заказам',
            'permissions.products' => 'Доступ к номенклатуре',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => array_merge($this->input('permissions', []), [
                'warehouses' => $this->boolean('permissions.warehouses'),
                'company_profile' => $this->boolean('permissions.company_profile'),
                'claims' => $this->boolean('permissions.claims'),
            ]),
        ]);
    }
}

<?php

namespace App\Http\Requests\EndCompany;

use App\Services\EndCompanyStaffAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEndCompanyEmployeeRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return app(EndCompanyStaffAccess::class)->canManageEmployees($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orderKeys = array_keys(config('end_company_staff.orders', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.orders' => ['required', Rule::in($orderKeys)],
            'permissions.own_orders_only' => ['nullable', 'boolean'],
            'permissions.catalog' => ['nullable', 'boolean'],
            'permissions.company_profile' => ['nullable', 'boolean'],
            'permissions.employees' => ['nullable', 'boolean'],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => array_merge($this->input('permissions', []), [
                'own_orders_only' => $this->boolean('permissions.own_orders_only'),
                'catalog' => $this->boolean('permissions.catalog'),
                'company_profile' => $this->boolean('permissions.company_profile'),
                'employees' => $this->boolean('permissions.employees'),
            ]),
        ]);
    }
}

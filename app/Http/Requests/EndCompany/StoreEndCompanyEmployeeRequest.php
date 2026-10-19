<?php

namespace App\Http\Requests\EndCompany;

use App\Services\EndCompanyStaffAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreEndCompanyEmployeeRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'employee_role_id' => ['required', 'integer'],
            'delivery_address_ids' => ['nullable', 'array'],
            'delivery_address_ids.*' => ['integer'],
            'all_addresses' => ['nullable', 'boolean'],
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
            'comments' => 'Комментарии',
            'employee_role_id' => 'Роль сотрудника',
            'delivery_address_ids' => 'Филиалы',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'all_addresses' => $this->boolean('all_addresses'),
        ]);
    }
}

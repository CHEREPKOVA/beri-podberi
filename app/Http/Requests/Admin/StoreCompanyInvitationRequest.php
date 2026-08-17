<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('companies.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_types' => ['required', 'array', 'min:1'],
            'company_types.*' => ['required', 'string', Rule::in(Role::corporateSlugsWithEmployees())],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_types' => 'Желаемая роль',
            'company_types.*' => 'Роль',
            'full_name' => 'Название компании',
            'email' => 'Email получателя',
        ];
    }
}

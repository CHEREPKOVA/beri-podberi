<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AcceptCompanyInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'company_name' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'string', 'regex:/^\d{10,12}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'Пароль',
            'company_name' => 'Название компании',
            'inn' => 'ИНН',
        ];
    }
}

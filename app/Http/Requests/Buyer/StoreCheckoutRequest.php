<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
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
            'delivery_method_id' => ['required', 'integer', 'exists:delivery_methods,id'],
            'end_company_delivery_address_id' => ['nullable', 'integer', 'exists:end_company_delivery_addresses,id'],
            'transport_company_id' => ['nullable', 'integer', 'exists:transport_companies,id'],
            'buyer_comment' => ['nullable', 'string', 'max:2000'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_time_from' => ['nullable', 'date_format:H:i'],
            'delivery_time_to' => ['nullable', 'date_format:H:i'],
            'delivery_vehicle_type' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_method_id.required' => 'Выберите способ доставки.',
            'delivery_method_id.exists' => 'Способ доставки не найден.',
            'delivery_date.after_or_equal' => 'Дата доставки не может быть в прошлом.',
        ];
    }
}

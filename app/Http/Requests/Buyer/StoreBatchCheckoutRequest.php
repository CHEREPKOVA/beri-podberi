<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchCheckoutRequest extends FormRequest
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
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.group_key' => ['required', 'string', 'max:80'],
            'orders.*.delivery_method_id' => ['required', 'integer', 'exists:delivery_methods,id'],
            'orders.*.end_company_delivery_address_id' => ['nullable', 'integer', 'exists:end_company_delivery_addresses,id'],
            'orders.*.transport_company_id' => ['nullable', 'integer', 'exists:transport_companies,id'],
            'orders.*.buyer_comment' => ['nullable', 'string', 'max:2000'],
            'orders.*.delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'orders.*.delivery_time_from' => ['nullable', 'date_format:H:i'],
            'orders.*.delivery_time_to' => ['nullable', 'date_format:H:i'],
            'orders.*.delivery_vehicle_type' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orders.required' => 'Нет заказов для оформления.',
            'orders.min' => 'Нет заказов для оформления.',
            'orders.*.delivery_method_id.required' => 'Выберите способ доставки.',
            'orders.*.delivery_method_id.exists' => 'Способ доставки не найден.',
            'orders.*.delivery_date.after_or_equal' => 'Дата доставки не может быть в прошлом.',
        ];
    }
}

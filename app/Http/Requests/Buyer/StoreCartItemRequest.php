<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
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
            'distributor_product_id' => ['required', 'integer', 'exists:distributor_products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'distributor_product_id.required' => 'Выберите поставщика.',
            'distributor_product_id.exists' => 'Выбранный поставщик не найден.',
            'quantity.min' => 'Количество должно быть не меньше 1.',
        ];
    }
}

<?php

namespace App\Http\Requests\Distributor;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseCheckoutRequest extends FormRequest
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
            'distributor_warehouse_id' => ['nullable', 'integer', 'exists:distributor_warehouses,id'],
            'transport_company_id' => ['nullable', 'integer', 'exists:transport_companies,id'],
            'responsible_contact_id' => ['nullable', 'integer', 'exists:distributor_contacts,id'],
            'buyer_comment' => ['nullable', 'string', 'max:2000'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}

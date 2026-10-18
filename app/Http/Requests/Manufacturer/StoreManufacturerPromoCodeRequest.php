<?php

namespace App\Http\Requests\Manufacturer;

use App\Models\ManufacturerPromoCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManufacturerPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('manufacturer_promo_codes', 'code')->ignore($this->route('promoCode')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['required', Rule::in(array_keys(config('manufacturer_discounts.promo_discount_types', [])))],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in([
                ManufacturerPromoCode::STATUS_ACTIVE,
                ManufacturerPromoCode::STATUS_INACTIVE,
            ])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'boolean'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'apply_to_all_products' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'Код промокода',
            'discount_type' => 'Тип скидки',
            'discount_value' => 'Размер скидки',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code', ''))),
            'per_user_limit' => $this->boolean('per_user_limit'),
            'apply_to_all_products' => $this->boolean('apply_to_all_products'),
        ]);
        if ($this->boolean('activate')) {
            $this->merge(['status' => ManufacturerPromoCode::STATUS_ACTIVE]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('discount_type') === ManufacturerPromoCode::TYPE_PERCENT && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Процент скидки не может быть больше 100.');
            }
        });
    }
}

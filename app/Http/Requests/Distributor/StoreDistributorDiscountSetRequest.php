<?php

namespace App\Http\Requests\Distributor;

use App\Models\DistributorDiscountSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDistributorDiscountSetRequest extends FormRequest
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
        $types = array_keys(config('distributor_discounts.types', []));
        $valueKinds = array_keys(config('distributor_discounts.value_kinds', []));
        $audience = array_keys(config('distributor_discounts.audience_types', []));
        $companyTypes = array_keys(config('distributor_discounts.company_types', []));
        $sources = array_keys(config('distributor_discounts.sources', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in($types)],
            'value_kind' => ['required', Rule::in($valueKinds)],
            'status' => ['required', Rule::in([
                DistributorDiscountSet::STATUS_ACTIVE,
                DistributorDiscountSet::STATUS_INACTIVE,
            ])],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['integer'],
            'do_not_stack' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'source' => ['nullable', Rule::in($sources)],
            'apply_to_all_products' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['string', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'audience_type' => ['nullable', Rule::in($audience)],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer'],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer'],
            'company_types' => ['nullable', 'array'],
            'company_types.*' => [Rule::in($companyTypes)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_indefinite' => ['nullable', 'boolean'],
            'auto_deactivate' => ['nullable', 'boolean'],
            'confirm_conflicts' => ['nullable', 'boolean'],
            'activate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Название набора скидок',
            'type' => 'Тип скидки',
            'value_kind' => 'Размер скидки',
            'starts_at' => 'Дата начала',
            'ends_at' => 'Дата окончания',
            'discount_percent' => 'Процент скидки',
            'discount_amount' => 'Фиксированная сумма скидки',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('type');
            $valueKind = (string) $this->input('value_kind');

            if ($valueKind === DistributorDiscountSet::VALUE_PERCENT
                && ($this->input('discount_percent') === null || $this->input('discount_percent') === '')
            ) {
                $validator->errors()->add('discount_percent', 'Укажите процент скидки.');
            }

            if ($valueKind === DistributorDiscountSet::VALUE_FIXED
                && ($this->input('discount_amount') === null || $this->input('discount_amount') === '')
            ) {
                $validator->errors()->add('discount_amount', 'Укажите фиксированную сумму скидки.');
            }

            if ($type === DistributorDiscountSet::TYPE_CATEGORY && empty($this->input('category_ids'))) {
                $validator->errors()->add('category_ids', 'Выберите хотя бы одну категорию.');
            }

            if ($type === DistributorDiscountSet::TYPE_BRAND && empty($this->input('brands'))) {
                $validator->errors()->add('brands', 'Выберите хотя бы один бренд.');
            }

            if (
                $type === DistributorDiscountSet::TYPE_PRODUCTS
                && ! $this->boolean('apply_to_all_products')
                && empty($this->input('product_ids'))
            ) {
                $validator->errors()->add('product_ids', 'Выберите хотя бы один товар или отметьте «На все товары каталога».');
            }

            if (
                in_array($type, [DistributorDiscountSet::TYPE_PERSONAL, DistributorDiscountSet::TYPE_MASS], true)
                && ! $this->boolean('apply_to_all_products')
                && empty($this->input('product_ids'))
                && empty($this->input('category_ids'))
                && empty($this->input('brands'))
            ) {
                $validator->errors()->add('product_ids', 'Выберите товары или отметьте «На все товары каталога».');
            }

            if ($type === DistributorDiscountSet::TYPE_PERSONAL && empty($this->input('company_ids'))) {
                $validator->errors()->add('company_ids', 'Выберите компанию-получателя.');
            }

            if ($type === DistributorDiscountSet::TYPE_PERSONAL && is_array($this->input('company_ids')) && count($this->input('company_ids')) > 1) {
                $validator->errors()->add('company_ids', 'Персональная скидка назначается только одной компании.');
            }

            if ($type === DistributorDiscountSet::TYPE_MASS) {
                $audience = (string) $this->input('audience_type', DistributorDiscountSet::AUDIENCE_SELECTED);
                if ($audience === DistributorDiscountSet::AUDIENCE_SELECTED && empty($this->input('company_ids'))) {
                    $validator->errors()->add('company_ids', 'Выберите хотя бы одну компанию.');
                }
                if ($audience === DistributorDiscountSet::AUDIENCE_REGION && empty($this->input('region_ids'))) {
                    $validator->errors()->add('region_ids', 'Выберите регион клиентов.');
                }
                if ($audience === DistributorDiscountSet::AUDIENCE_COMPANY_TYPES && empty($this->input('company_types'))) {
                    $validator->errors()->add('company_types', 'Выберите типы компаний.');
                }
            }

            if (! $this->boolean('is_indefinite') && ! $this->filled('ends_at') && ! $this->filled('starts_at')) {
                // период необязателен — станет бессрочным
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $status = $this->boolean('activate')
            ? DistributorDiscountSet::STATUS_ACTIVE
            : (string) ($this->input('status') ?? DistributorDiscountSet::STATUS_INACTIVE);

        $this->merge([
            'do_not_stack' => $this->boolean('do_not_stack'),
            'apply_to_all_products' => $this->boolean('apply_to_all_products'),
            'is_indefinite' => $this->boolean('is_indefinite'),
            'auto_deactivate' => $this->boolean('auto_deactivate'),
            'confirm_conflicts' => $this->boolean('confirm_conflicts'),
            'activate' => $this->boolean('activate'),
            'status' => $status,
            'source' => $this->input('source') ?: DistributorDiscountSet::SOURCE_MANUAL,
        ]);
    }
}

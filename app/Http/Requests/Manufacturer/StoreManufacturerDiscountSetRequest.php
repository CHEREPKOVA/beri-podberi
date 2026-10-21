<?php

namespace App\Http\Requests\Manufacturer;

use App\Models\ManufacturerDiscountSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManufacturerDiscountSetRequest extends FormRequest
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
        $types = array_keys(config('manufacturer_discounts.types', []));
        $audience = array_keys(config('manufacturer_discounts.audience_types', []));
        $companyTypes = array_keys(config('manufacturer_discounts.company_types', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in($types)],
            'status' => ['required', Rule::in([
                ManufacturerDiscountSet::STATUS_ACTIVE,
                ManufacturerDiscountSet::STATUS_INACTIVE,
            ])],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'require_all_conditions' => ['nullable', 'boolean'],
            'apply_to_all_products' => ['nullable', 'boolean'],
            'apply_only_active_products' => ['nullable', 'boolean'],
            'only_new_distributors' => ['nullable', 'boolean'],
            'audience_type' => ['required', Rule::in($audience)],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'excluded_category_ids' => ['nullable', 'array'],
            'excluded_category_ids.*' => ['integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer'],
            'audience_region_ids' => ['nullable', 'array'],
            'audience_region_ids.*' => ['integer'],
            'distributor_ids' => ['nullable', 'array'],
            'distributor_ids.*' => ['integer'],
            'company_types' => ['nullable', 'array'],
            'company_types.*' => [Rule::in($companyTypes)],
            'tiers' => ['nullable', 'array'],
            'tiers.*.min_amount' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.min_quantity' => ['nullable', 'integer', 'min:1'],
            'tiers.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tiers.*.amount' => ['nullable', 'numeric', 'min:0'],
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
            $applyAll = $this->boolean('apply_to_all_products');

            if (in_array($type, [ManufacturerDiscountSet::TYPE_PERCENT, ManufacturerDiscountSet::TYPE_CATEGORY, ManufacturerDiscountSet::TYPE_INDIVIDUAL, ManufacturerDiscountSet::TYPE_REGION, ManufacturerDiscountSet::TYPE_PROMO], true)
                && ($this->input('discount_percent') === null || $this->input('discount_percent') === '')
                && ($this->input('discount_amount') === null || $this->input('discount_amount') === '')
            ) {
                $validator->errors()->add('discount_percent', 'Укажите процент или сумму скидки.');
            }

            if ($type === ManufacturerDiscountSet::TYPE_FIXED && ($this->input('discount_amount') === null || $this->input('discount_amount') === '')) {
                $validator->errors()->add('discount_amount', 'Укажите фиксированную сумму скидки.');
            }

            if ($type === ManufacturerDiscountSet::TYPE_VOLUME_TIER) {
                $tiers = collect($this->input('tiers', []))->filter(function ($tier): bool {
                    return is_array($tier) && (($tier['min_amount'] ?? '') !== '' || ($tier['min_quantity'] ?? '') !== '');
                });
                if ($tiers->isEmpty()) {
                    $validator->errors()->add('tiers', 'Добавьте хотя бы одну ступень градации.');
                }
            }

            if ($type === ManufacturerDiscountSet::TYPE_CATEGORY && empty($this->input('category_ids'))) {
                $validator->errors()->add('category_ids', 'Выберите категории товаров.');
            }

            if ($type === ManufacturerDiscountSet::TYPE_INDIVIDUAL) {
                $audience = (string) $this->input('audience_type');
                if ($audience !== '' && $audience !== ManufacturerDiscountSet::AUDIENCE_SELECTED) {
                    $validator->errors()->add('audience_type', 'Индивидуальная скидка привязывается только к выбранному дистрибьютору.');
                }
                $distributorIds = array_values(array_filter(array_map('intval', (array) $this->input('distributor_ids', []))));
                if (count($distributorIds) !== 1) {
                    $validator->errors()->add('distributor_ids', 'Выберите ровно одного дистрибьютора для индивидуальной скидки.');
                }
            }

            if ($type === ManufacturerDiscountSet::TYPE_REGION && empty($this->input('region_ids')) && empty($this->input('audience_region_ids'))) {
                $validator->errors()->add('region_ids', 'Укажите регион действия скидки.');
            }

            if ($this->input('audience_type') === ManufacturerDiscountSet::AUDIENCE_SELECTED && empty($this->input('distributor_ids'))) {
                $validator->errors()->add('distributor_ids', 'Выберите хотя бы одного дистрибьютора.');
            }

            if ($this->input('audience_type') === ManufacturerDiscountSet::AUDIENCE_COMPANY_TYPES && empty($this->input('company_types'))) {
                $validator->errors()->add('company_types', 'Выберите типы компаний.');
            }

            if ($this->input('audience_type') === ManufacturerDiscountSet::AUDIENCE_REGION && empty($this->input('audience_region_ids')) && empty($this->input('region_ids'))) {
                $validator->errors()->add('audience_region_ids', 'Выберите регионы дистрибьюторов.');
            }

            if (! $applyAll && empty($this->input('category_ids')) && empty($this->input('product_ids')) && $type !== ManufacturerDiscountSet::TYPE_VOLUME_TIER) {
                // допустимо для percent/fixed, если пользователь забыл галочку «все товары» — мягко не блокируем кроме category
            }

            $isIndefinite = $this->boolean('is_indefinite');
            if ($type === ManufacturerDiscountSet::TYPE_PROMO && ! $isIndefinite && ! $this->filled('ends_at') && ! $this->filled('starts_at')) {
                $validator->errors()->add('starts_at', 'Для промо-скидки укажите период действия или отметьте бессрочность.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $status = $this->boolean('activate')
            ? ManufacturerDiscountSet::STATUS_ACTIVE
            : (string) ($this->input('status') ?? ManufacturerDiscountSet::STATUS_INACTIVE);

        $merge = [
            'require_all_conditions' => $this->boolean('require_all_conditions'),
            'apply_to_all_products' => $this->boolean('apply_to_all_products'),
            'apply_only_active_products' => $this->boolean('apply_only_active_products'),
            'only_new_distributors' => $this->boolean('only_new_distributors'),
            'is_indefinite' => $this->boolean('is_indefinite'),
            'auto_deactivate' => $this->boolean('auto_deactivate'),
            'confirm_conflicts' => $this->boolean('confirm_conflicts'),
            'activate' => $this->boolean('activate'),
            'status' => $status,
        ];

        if ((string) $this->input('type') === ManufacturerDiscountSet::TYPE_INDIVIDUAL) {
            $merge['audience_type'] = ManufacturerDiscountSet::AUDIENCE_SELECTED;
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('distributor_ids', [])))));
            $merge['distributor_ids'] = array_slice($ids, 0, 1);
        }

        $this->merge($merge);
    }
}

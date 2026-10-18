@php
    $brandControl = 'form-control-brand';
    $fieldName = static function (string $field) use ($namePrefix): string {
        return $namePrefix === '' ? $field : $namePrefix.'['.$field.']';
    };
    $oldKey = static function (string $field) use ($dotPrefix): string {
        return $dotPrefix === '' ? $field : $dotPrefix.'.'.$field;
    };
    $defaultMethodId = old($oldKey('delivery_method_id'), $deliveryMethods->first()?->id);
@endphp

<div class="space-y-6" x-data="{
    deliveryMethodId: @js($defaultMethodId !== null ? (int) $defaultMethodId : null),
    transportSlug: 'transport_company',
    ownTransportSlug: 'own_transport',
    selfPickupSlug: 'self_pickup',
    methods: @js($deliveryMethods->map(fn ($m) => ['id' => $m->id, 'slug' => $m->slug])->values()),
    currentSlug() {
        return this.methods.find(m => Number(m.id) === Number(this.deliveryMethodId))?.slug || null;
    }
}">
    @if(!empty($group['group_key']))
        <input type="hidden" name="{{ $fieldName('group_key') }}" value="{{ $group['group_key'] }}">
    @endif

    @if(count($priceChangeNotices))
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100">
            <p class="font-medium mb-1">Цены изменились — заказ будет создан по актуальным ценам:</p>
            <ul class="list-disc list-inside">
                @foreach($priceChangeNotices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(count($itemWarnings))
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100">
            <p class="font-medium mb-1">Обратите внимание на позиции:</p>
            <ul class="space-y-2">
                @foreach($itemWarnings as $entry)
                    <li>
                        <span class="font-medium">{{ $entry['name'] }}</span>
                        <ul class="list-disc list-inside ml-2">
                            @foreach($entry['warnings'] as $warning)
                                <li class="{{ $warning['blocking'] ? 'text-red-700 dark:text-red-300' : '' }}">{{ $warning['message'] }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($deliveryNotes)
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
            <p class="font-medium mb-1">Особые условия поставщика</p>
            <p>{{ $deliveryNotes }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Способ доставки</h2>
                @error($oldKey('delivery_method_id'))
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div class="space-y-2">
                    @forelse($deliveryMethods as $method)
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700 p-3 cursor-pointer hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:ring-2 has-[:checked]:ring-[#c3242a]/20">
                            <input type="radio"
                                   name="{{ $fieldName('delivery_method_id') }}"
                                   value="{{ $method->id }}"
                                   class="mt-1 accent-[#c3242a] js-delivery-method"
                                   x-model.number="deliveryMethodId"
                                   @checked((string) old($oldKey('delivery_method_id'), $deliveryMethods->first()?->id) === (string) $method->id)>
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $method->name }}</span>
                                @if($method->description)
                                    <span class="block text-gray-500">{{ $method->description }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-red-600">Нет доступных способов доставки.</p>
                    @endforelse
                </div>

                <div x-show="currentSlug() === transportSlug" x-cloak class="pt-2 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Транспортная компания</label>
                        <div class="relative">
                            <select name="{{ $fieldName('transport_company_id') }}"
                                    class="{{ $brandControl }} appearance-none pr-10 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                                    :disabled="currentSlug() !== transportSlug">
                                <option value="">Выберите ТК</option>
                                @foreach($transportCompanies as $company)
                                    <option value="{{ $company->id }}" @selected((string) old($oldKey('transport_company_id')) === (string) $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                        @error($oldKey('transport_company_id'))
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Желаемая дата отгрузки</label>
                        <input type="text"
                               name="{{ $fieldName('delivery_date') }}"
                               value="{{ old($oldKey('delivery_date')) }}"
                               placeholder="Выберите дату"
                               class="js-flatpickr {{ $brandControl }}"
                               autocomplete="off"
                               :disabled="currentSlug() !== transportSlug">
                    </div>
                </div>

                <div x-show="currentSlug() === ownTransportSlug" x-cloak class="pt-2 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Дата доставки *</label>
                        <input type="text"
                               name="{{ $fieldName('delivery_date') }}"
                               value="{{ old($oldKey('delivery_date')) }}"
                               placeholder="Выберите дату"
                               class="js-flatpickr {{ $brandControl }}"
                               autocomplete="off"
                               :disabled="currentSlug() !== ownTransportSlug">
                        @error($oldKey('delivery_date'))
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Время с</label>
                            <input type="time" name="{{ $fieldName('delivery_time_from') }}" value="{{ old($oldKey('delivery_time_from')) }}"
                                   class="{{ $brandControl }}"
                                   :disabled="currentSlug() !== ownTransportSlug">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Время до</label>
                            <input type="time" name="{{ $fieldName('delivery_time_to') }}" value="{{ old($oldKey('delivery_time_to')) }}"
                                   class="{{ $brandControl }}"
                                   :disabled="currentSlug() !== ownTransportSlug">
                            @error($oldKey('delivery_time_to'))
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Тип транспорта</label>
                        <input type="text" name="{{ $fieldName('delivery_vehicle_type') }}" value="{{ old($oldKey('delivery_vehicle_type')) }}"
                               placeholder="Например: фура, газель"
                               class="{{ $brandControl }}"
                               :disabled="currentSlug() !== ownTransportSlug">
                    </div>
                </div>

                <div x-show="currentSlug() === selfPickupSlug" x-cloak class="pt-2 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Дата самовывоза</label>
                        <input type="text"
                               name="{{ $fieldName('delivery_date') }}"
                               value="{{ old($oldKey('delivery_date')) }}"
                               placeholder="Выберите дату"
                               class="js-flatpickr {{ $brandControl }}"
                               autocomplete="off"
                               :disabled="currentSlug() !== selfPickupSlug">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Время самовывоза</label>
                        <input type="time" name="{{ $fieldName('delivery_time_from') }}" value="{{ old($oldKey('delivery_time_from')) }}"
                               class="{{ $brandControl }}"
                               :disabled="currentSlug() !== selfPickupSlug">
                    </div>
                </div>
            </section>

            <section x-show="currentSlug() === transportSlug" x-cloak
                     class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Склад / адрес получения</h2>
                @error($oldKey('end_company_delivery_address_id'))
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if($deliveryAddresses->isEmpty())
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        Адреса получения в регионе этого заказа не добавлены.
                        <a href="{{ route('end_company.profile', ['tab' => 'delivery']) }}" class="underline hover:text-[#c3242a]">Добавить в профиле</a>
                    </p>
                @else
                    <div class="space-y-2">
                        @foreach($deliveryAddresses as $address)
                            <label class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700 p-3 cursor-pointer hover:border-[#c3242a] has-[:checked]:border-[#c3242a] has-[:checked]:ring-2 has-[:checked]:ring-[#c3242a]/20">
                                <input type="radio"
                                       name="{{ $fieldName('end_company_delivery_address_id') }}"
                                       value="{{ $address->id }}"
                                       class="mt-1 accent-[#c3242a]"
                                       :disabled="currentSlug() !== transportSlug"
                                       @checked((string) old($oldKey('end_company_delivery_address_id'), $defaultAddressId) === (string) $address->id)>
                                <span class="text-sm">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $address->name }}</span>
                                    @if($address->is_default)
                                        <span class="ml-1 text-xs text-gray-500">(по умолчанию)</span>
                                    @endif
                                    <span class="block text-gray-600 dark:text-gray-300">{{ $address->address }}</span>
                                    <span class="block text-xs text-gray-500">{{ $address->region?->name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Комментарий к заказу</h2>
                <textarea name="{{ $fieldName('buyer_comment') }}"
                          rows="3"
                          maxlength="2000"
                          placeholder="Например: просьба упаковать на паллету"
                          class="{{ $brandControl }}">{{ old($oldKey('buyer_comment')) }}</textarea>
            </section>
        </div>

        <div class="space-y-6">
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Состав заказа</h2>
                <ul class="space-y-3">
                    @foreach($group['items'] as $item)
                        <li class="border-b border-gray-100 dark:border-gray-700 pb-3 last:border-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $item['sku'] ?: '—' }} · {{ $item['quantity'] }} шт.
                                @if($item['has_discount'] ?? false)
                                    · <span class="line-through">{{ $item['list_unit_price_formatted'] }}</span>
                                    {{ $item['unit_price_formatted'] }}
                                @else
                                    × {{ $item['unit_price_formatted'] }}
                                @endif
                            </p>
                            @if(($item['discount_amount'] ?? 0) > 0)
                                <p class="text-xs text-green-700 dark:text-green-300">Скидка: −{{ number_format($item['discount_amount'], 2, ',', ' ') }} ₽</p>
                            @endif
                            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $item['line_total_formatted'] }}</p>
                        </li>
                    @endforeach
                </ul>
                @if(($group['discount_amount'] ?? 0) > 0)
                    <p class="mt-3 text-sm text-green-700 dark:text-green-300">
                        Экономия по скидкам: −{{ number_format($group['discount_amount'], 2, ',', ' ') }} ₽
                    </p>
                @endif
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <span class="text-sm text-gray-500">Итого</span>
                    <span class="text-xl font-semibold text-[#c3242a]">{{ number_format($group['subtotal'], 2, ',', ' ') }} ₽</span>
                </div>
            </section>
        </div>
    </div>
</div>

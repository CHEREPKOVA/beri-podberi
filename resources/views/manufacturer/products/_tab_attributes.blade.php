<div class="space-y-6">
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Характеристики товара</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
            Добавьте технические и маркетинговые параметры товара для использования в фильтрах каталога.
        </p>

        @if($attributes->isEmpty())
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6 text-center">
            <svg class="mx-auto w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-gray-600 dark:text-gray-400">Характеристики пока не настроены в системе</p>
        </div>
        @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach($attributes as $attribute)
            @php
                $existingValue = $product?->attributeValues->firstWhere('product_attribute_id', $attribute->id);
            @endphp
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ $attribute->name }}
                    @if($attribute->is_required)
                    <span class="text-red-500">*</span>
                    @endif
                    @if($attribute->is_filterable)
                    <span class="ml-1 text-xs text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">фильтр</span>
                    @endif
                </label>

                @switch($attribute->type)
                    @case('text')
                        <input type="text" name="attributes[{{ $attribute->id }}]"
                            value="{{ old('attributes.' . $attribute->id, $existingValue?->value) }}"
                            {{ $attribute->is_required ? 'required' : '' }}
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        @break

                    @case('number')
                        <input type="number" name="attributes[{{ $attribute->id }}]"
                            value="{{ old('attributes.' . $attribute->id, $existingValue?->value) }}"
                            step="any"
                            {{ $attribute->is_required ? 'required' : '' }}
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        @break

                    @case('select')
                        <div class="relative">
                            <select name="attributes[{{ $attribute->id }}]"
                                {{ $attribute->is_required ? 'required' : '' }}
                                class="w-full appearance-none pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                                <option value="">Выберите...</option>
                                @foreach($attribute->options ?? [] as $option)
                                <option value="{{ $option }}" {{ old('attributes.' . $attribute->id, $existingValue?->value) === $option ? 'selected' : '' }}>
                                    {{ $option }}
                                </option>
                                @endforeach
                            </select>
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        @break

                    @case('boolean')
                        @php
                            $isChecked = old('attributes.' . $attribute->id, $existingValue?->value) === '1';
                        @endphp
                        <div x-data="{ checked: {{ $isChecked ? 'true' : 'false' }} }" class="pt-1">
                            <label class="flex cursor-pointer items-center select-none">
                                <div class="relative">
                                    <input type="hidden" name="attributes[{{ $attribute->id }}]" :value="checked ? '1' : '0'">
                                    <div @click="checked = !checked"
                                        :class="checked ? 'border-[#c3242a] bg-[#c3242a]' : 'bg-transparent border-gray-300 dark:border-gray-600'"
                                        class="flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-colors hover:border-[#c3242a] dark:hover:border-[#c3242a] cursor-pointer">
                                        <span :class="checked ? 'opacity-100' : 'opacity-0'" class="transition-opacity">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                                <span class="ml-2.5 text-sm text-gray-600 dark:text-gray-400" x-text="checked ? 'Да' : 'Нет'"></span>
                            </label>
                        </div>
                        @break
                @endswitch
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div x-data="{ customAttributes: {{ json_encode($product?->attributeValues->filter(fn($av) => !$attributes->contains('id', $av->product_attribute_id))->map(fn($av) => ['key' => $av->attribute?->name ?? '', 'value' => $av->value])->values()->toArray() ?? []) }} }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Дополнительные характеристики</h3>
            <button type="button" @click="customAttributes.push({key: '', value: ''})"
                class="inline-flex items-center gap-1 text-sm text-[#c3242a] hover:text-[#a01e24]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Добавить характеристику
            </button>
        </div>

        <div class="space-y-3">
            <template x-for="(attr, index) in customAttributes" :key="index">
                <div class="flex items-center gap-3">
                    <input type="text" x-model="attr.key" :name="'custom_attributes['+index+'][key]'"
                        placeholder="Название характеристики"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    <input type="text" x-model="attr.value" :name="'custom_attributes['+index+'][value]'"
                        placeholder="Значение"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    <button type="button" @click="customAttributes.splice(index, 1)"
                        class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        <div x-show="customAttributes.length === 0" class="text-sm text-gray-500 mt-2">
            Нажмите "Добавить характеристику" чтобы добавить свои параметры
        </div>
    </div>
</div>

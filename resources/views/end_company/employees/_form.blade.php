@php
    $addressIds = collect($addressIds ?? []);
    $allAddresses = $allAddresses ?? false;
    $selectedRoleId = old('employee_role_id', $roleId ?? ($roles[0]->id ?? ''));
    $checkboxBox = 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]';
@endphp

<div
    x-data="endCompanyEmployeeForm({
        allAddresses: {{ json_encode((bool) old('all_addresses', $allAddresses)) }},
        addressIds: {{ json_encode(array_map('strval', old('delivery_address_ids', $addressIds->all()))) }},
        allAddressIds: {{ json_encode($addresses->pluck('id')->map(fn ($id) => (string) $id)->values()->all()) }}
    })"
    class="space-y-6"
>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                ФИО <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name" required maxlength="255"
                value="{{ old('name', $employee->name ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Email (логин) <span class="text-red-500">*</span>
            </label>
            <input type="email" id="email" name="email" required maxlength="255"
                value="{{ old('email', $employee->email ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="position" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Должность</label>
            <input type="text" id="position" name="position" maxlength="255"
                value="{{ old('position', $position ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25">
        </div>
        <div>
            <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Телефон</label>
            <input type="text" id="phone" name="phone" maxlength="50"
                value="{{ old('phone', $phone ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25">
        </div>
    </div>

    <div>
        <label for="comments" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Комментарии</label>
        <textarea id="comments" name="comments" rows="3" maxlength="2000"
            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25">{{ old('comments', $comments ?? '') }}</textarea>
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Роль сотрудника <span class="text-red-500">*</span>
        </label>
        @include('manufacturer.products._flat_select', [
            'name' => 'employee_role_id',
            'placeholder' => 'Выберите роль',
            'options' => collect($roles)->mapWithKeys(fn ($role) => [(string) $role->id => $role->name])->all(),
            'selected' => (string) $selectedRoleId,
            'required' => true,
        ])
        @error('employee_role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500">Права доступа задаются в выбранной роли. Изменить их можно на вкладке «Роли».</p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Привязка к филиалам</h3>
            @if($addresses->isNotEmpty())
                <label class="inline-flex items-center gap-2.5 text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
                    <input type="checkbox" name="all_addresses" value="1" x-model="allAddresses" @change="toggleAllAddresses()" class="sr-only peer">
                    <span class="{{ $checkboxBox }}">
                        <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    Вся компания
                </label>
            @endif
        </div>
        @if($addresses->isEmpty())
            <p class="text-sm text-gray-500">У компании пока нет филиалов. Их можно добавить в профиле как адреса доставки.</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($addresses as $address)
                    <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                        <input type="checkbox" name="delivery_address_ids[]" value="{{ $address->id }}"
                            :checked="addressIds.includes('{{ $address->id }}')"
                            @change="onAddressChange('{{ $address->id }}', $event.target.checked)"
                            class="sr-only peer">
                        <span class="{{ $checkboxBox }}">
                            <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        {{ $address->name ?: $address->address }}
                        @if($address->region?->name)
                            <span class="text-xs text-gray-400">({{ $address->region->name }})</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500">Если выбран один филиал, его адрес подставляется при оформлении заказа. Сотрудник не видит заказы других филиалов.</p>
        @endif
    </div>
</div>

@once
@push('scripts')
<script>
function endCompanyEmployeeForm(config) {
    return {
        allAddresses: Boolean(config.allAddresses),
        allAddressIds: (config.allAddressIds || []).map(String),
        addressIds: (config.addressIds || []).map(String),
        init() {
            if (this.allAddresses) {
                this.addressIds = [...this.allAddressIds];
            }
            this.syncAddressDom();
        },
        toggleAllAddresses() {
            this.addressIds = this.allAddresses ? [...this.allAddressIds] : [];
            this.syncAddressDom();
        },
        onAddressChange(id, checked) {
            id = String(id);
            if (checked) {
                if (!this.addressIds.includes(id)) {
                    this.addressIds.push(id);
                }
            } else {
                this.addressIds = this.addressIds.filter((value) => value !== id);
            }
            this.allAddresses = this.allAddressIds.length > 0
                && this.addressIds.length === this.allAddressIds.length;
            this.syncAddressDom();
        },
        syncAddressDom() {
            const selected = this.addressIds.map(String);
            this.$nextTick(() => {
                this.$el.querySelectorAll('input[name="delivery_address_ids[]"]').forEach((input) => {
                    input.checked = selected.includes(String(input.value));
                });
            });
        }
    };
}
</script>
@endpush
@endonce

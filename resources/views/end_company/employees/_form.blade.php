@php
    $permissions = $permissions ?? \App\Models\EndCompanyEmployeeRole::normalizePermissions([]);
    $addressIds = collect($addressIds ?? []);
    $allAddresses = $allAddresses ?? false;
    $selectedRoleId = old('employee_role_id', $roleId ?? ($roles[0]->id ?? ''));
    $rolesPayload = collect($roles)->map(fn ($role) => [
        'id' => $role->id,
        'name' => $role->name,
        'permissions' => $role->normalizedPermissions(),
    ])->values();
@endphp

<div
    x-data="endCompanyEmployeeForm({
        roleId: {{ json_encode((string) old('employee_role_id', $selectedRoleId)) }},
        roles: {{ json_encode($rolesPayload) }},
        permissions: {{ json_encode(old('permissions', $permissions)) }},
        allAddresses: {{ json_encode((bool) old('all_addresses', $allAddresses)) }},
        addressIds: {{ json_encode(array_map('strval', old('delivery_address_ids', $addressIds->all()))) }}
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
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Email (логин) <span class="text-red-500">*</span>
            </label>
            <input type="email" id="email" name="email" required maxlength="255"
                value="{{ old('email', $employee->email ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="position" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Должность</label>
            <input type="text" id="position" name="position" maxlength="255"
                value="{{ old('position', $position ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
        </div>
        <div>
            <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Телефон</label>
            <input type="text" id="phone" name="phone" maxlength="50"
                value="{{ old('phone', $phone ?? '') }}"
                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
        </div>
    </div>

    <div>
        <label for="comments" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Комментарии</label>
        <textarea id="comments" name="comments" rows="3" maxlength="2000"
            class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">{{ old('comments', $comments ?? '') }}</textarea>
    </div>

    <div>
        <label for="employee_role_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Роль сотрудника <span class="text-red-500">*</span>
        </label>
        <select id="employee_role_id" name="employee_role_id" x-model="roleId" @change="applyRole()"
            class="w-full appearance-none px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
            <option value="">Выберите роль</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>
        @error('employee_role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500">Права роли подставятся автоматически, их можно изменить ниже.</p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Права и доступы</h3>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Заказы</label>
            <select name="permissions[orders]" x-model="permissions.orders"
                class="w-full appearance-none px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                @foreach($orderOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[own_orders_only]" value="1" x-model="permissions.own_orders_only" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Только свои заказы
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[catalog]" value="1" x-model="permissions.catalog" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Каталог товаров
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[company_profile]" value="1" x-model="permissions.company_profile" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Профиль компании
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[employees]" value="1" x-model="permissions.employees" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Раздел сотрудников
            </label>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Привязка к филиалам</h3>
            @if($addresses->isNotEmpty())
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="all_addresses" value="1" x-model="allAddresses"
                        class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                    Вся компания
                </label>
            @endif
        </div>
        @if($addresses->isEmpty())
            <p class="text-sm text-gray-500">У компании пока нет филиалов. Их можно добавить в профиле как адреса доставки.</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($addresses as $address)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="delivery_address_ids[]" value="{{ $address->id }}"
                            :checked="allAddresses || addressIds.includes('{{ $address->id }}')"
                            @change="syncAddress('{{ $address->id }}', $event.target.checked)"
                            class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
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
        roleId: String(config.roleId || ''),
        roles: config.roles || [],
        permissions: Object.assign({
            orders: 'none',
            own_orders_only: false,
            catalog: false,
            company_profile: false,
            employees: false
        }, config.permissions || {}),
        allAddresses: Boolean(config.allAddresses),
        addressIds: (config.addressIds || []).map(String),
        applyRole() {
            const role = this.roles.find(item => String(item.id) === String(this.roleId));
            if (!role) return;
            this.permissions = Object.assign({}, role.permissions);
        },
        syncAddress(id, checked) {
            id = String(id);
            if (checked) {
                if (!this.addressIds.includes(id)) this.addressIds.push(id);
            } else {
                this.addressIds = this.addressIds.filter(value => value !== id);
                this.allAddresses = false;
            }
        }
    };
}
</script>
@endpush
@endonce

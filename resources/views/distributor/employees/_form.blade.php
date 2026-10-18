@php
    $permissions = $permissions ?? \App\Models\DistributorEmployeeRole::normalizePermissions([]);
    $warehouseIds = collect($warehouseIds ?? []);
    $allWarehouses = $allWarehouses ?? false;
    $selectedRoleId = old('employee_role_id', $roleId ?? ($roles[0]->id ?? ''));
    $rolesPayload = collect($roles)->map(fn ($role) => [
        'id' => $role->id,
        'name' => $role->name,
        'permissions' => $role->normalizedPermissions(),
    ])->values();
@endphp

<div
    x-data="distributorEmployeeForm({
        roleId: {{ json_encode((string) old('employee_role_id', $selectedRoleId)) }},
        roles: {{ json_encode($rolesPayload) }},
        permissions: {{ json_encode(old('permissions', $permissions)) }},
        allWarehouses: {{ json_encode((bool) old('all_warehouses', $allWarehouses)) }},
        warehouseIds: {{ json_encode(array_map('strval', old('warehouse_ids', $warehouseIds->all()))) }}
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Заказы («Мои заказы»)</label>
                <select name="permissions[orders]" x-model="permissions.orders"
                    class="w-full appearance-none px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($orderOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Покупки у производителей</label>
                <select name="permissions[purchases]" x-model="permissions.purchases"
                    class="w-full appearance-none px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($purchaseOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Номенклатура</label>
                <select name="permissions[products]" x-model="permissions.products"
                    class="w-full appearance-none px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
                    @foreach($productOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[prices]" value="1" x-model="permissions.prices" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Изменение цен
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[warehouses]" value="1" x-model="permissions.warehouses" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Склады и остатки
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[employees]" value="1" x-model="permissions.employees" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Раздел сотрудников
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[discounts]" value="1" x-model="permissions.discounts" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Модуль скидок
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[import]" value="1" x-model="permissions.import" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Импорт данных
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[manufacturer_chat]" value="1" x-model="permissions.manufacturer_chat" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Переписка с производителем
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="permissions[company_profile]" value="1" x-model="permissions.company_profile" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                Профиль компании
            </label>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Привязка к филиалам и складам</h3>
            @if($warehouses->isNotEmpty())
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="all_warehouses" value="1" x-model="allWarehouses"
                        class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                    Все склады
                </label>
            @endif
        </div>
        @if($warehouses->isEmpty())
            <p class="text-sm text-gray-500">У компании пока нет складов. Их можно добавить в разделе «Склады».</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($warehouses as $warehouse)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}"
                            :checked="allWarehouses || warehouseIds.includes('{{ $warehouse->id }}')"
                            @change="syncWarehouse('{{ $warehouse->id }}', $event.target.checked)"
                            class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                        {{ $warehouse->name }}
                        @if($warehouse->region?->name)
                            <span class="text-xs text-gray-400">({{ $warehouse->region->name }})</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500">Если выбран один склад, он подставляется при создании заказа. Остатки чужих складов сотруднику недоступны.</p>
        @endif
    </div>
</div>

@once
@push('scripts')
<script>
function distributorEmployeeForm(config) {
    return {
        roleId: String(config.roleId || ''),
        roles: config.roles || [],
        permissions: Object.assign({
            orders: 'none',
            purchases: 'none',
            products: 'none',
            prices: false,
            warehouses: false,
            employees: false,
            discounts: false,
            import: false,
            manufacturer_chat: false,
            company_profile: false
        }, config.permissions || {}),
        allWarehouses: Boolean(config.allWarehouses),
        warehouseIds: (config.warehouseIds || []).map(String),
        applyRole() {
            const role = this.roles.find(item => String(item.id) === String(this.roleId));
            if (!role) return;
            this.permissions = Object.assign({}, role.permissions);
        },
        syncWarehouse(id, checked) {
            id = String(id);
            if (checked) {
                if (!this.warehouseIds.includes(id)) this.warehouseIds.push(id);
            } else {
                this.warehouseIds = this.warehouseIds.filter(value => value !== id);
                this.allWarehouses = false;
            }
        }
    };
}
</script>
@endpush
@endonce

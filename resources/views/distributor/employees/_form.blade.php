@php
    $warehouseIds = collect($warehouseIds ?? []);
    $allWarehouses = $allWarehouses ?? false;
    $selectedRoleId = old('employee_role_id', $roleId ?? ($roles[0]->id ?? ''));
    $rolesPayload = collect($roles)->map(fn ($role) => [
        'id' => $role->id,
        'name' => $role->name,
    ])->values();
    $checkboxBox = 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]';
@endphp

<div
    x-data="distributorEmployeeForm({
        roleId: {{ json_encode((string) old('employee_role_id', $selectedRoleId)) }},
        roles: {{ json_encode($rolesPayload) }},
        allWarehouses: {{ json_encode((bool) old('all_warehouses', $allWarehouses)) }},
        warehouseIds: {{ json_encode(array_map('strval', old('warehouse_ids', $warehouseIds->all()))) }},
        allWarehouseIds: {{ json_encode($warehouses->pluck('id')->map(fn ($id) => (string) $id)->values()->all()) }}
    })"
    class="space-y-6"
    @keydown.escape.window="openSelect = ''"
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
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Роль сотрудника <span class="text-red-500">*</span>
        </label>
        @include('distributor.employees._styled_select', [
            'name' => 'employee_role_id',
            'openKey' => 'role',
            'valueExpr' => 'roleId',
            'optionsExpr' => 'roleOptions',
            'placeholder' => 'Выберите роль',
            'required' => true,
        ])
        @error('employee_role_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500">Права доступа задаются в выбранной роли. Изменить их можно на вкладке «Роли».</p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Привязка к филиалам и складам</h3>
            @if($warehouses->isNotEmpty())
                <label class="inline-flex items-center gap-2.5 text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
                    <input type="checkbox" name="all_warehouses" value="1" x-model="allWarehouses" @change="toggleAllWarehouses()" class="sr-only peer">
                    <span class="{{ $checkboxBox }}">
                        <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    Все склады
                </label>
            @endif
        </div>
        @if($warehouses->isEmpty())
            <p class="text-sm text-gray-500">У компании пока нет складов. Их можно добавить в разделе «Склады».</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($warehouses as $warehouse)
                    <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                        <input type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}"
                            :checked="warehouseIds.includes('{{ $warehouse->id }}')"
                            @change="onWarehouseChange('{{ $warehouse->id }}', $event.target.checked)"
                            class="sr-only peer">
                        <span class="{{ $checkboxBox }}">
                            <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
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
    const roles = Array.isArray(config.roles) ? config.roles : [];
    // Стабильный массив: getter с .map() даёт новый reference на каждый доступ и Alpine x-for дублирует пункты.
    const roleOptions = roles.map((role) => ({
        value: String(role.id),
        label: role.name,
    }));

    return {
        openSelect: '',
        roleId: String(config.roleId || ''),
        roles,
        roleOptions,
        allWarehouses: Boolean(config.allWarehouses),
        allWarehouseIds: (config.allWarehouseIds || []).map(String),
        warehouseIds: (config.warehouseIds || []).map(String),
        optionLabel(options, value, placeholder) {
            const current = String(value ?? '');
            const item = (options || []).find((option) => String(option.value) === current);
            return item ? item.label : (placeholder || 'Выберите');
        },
        init() {
            if (this.allWarehouses) {
                this.warehouseIds = [...this.allWarehouseIds];
            }
            this.syncWarehouseDom();
        },
        toggleAllWarehouses() {
            this.warehouseIds = this.allWarehouses ? [...this.allWarehouseIds] : [];
            this.syncWarehouseDom();
        },
        onWarehouseChange(id, checked) {
            id = String(id);
            if (checked) {
                if (!this.warehouseIds.includes(id)) {
                    this.warehouseIds.push(id);
                }
            } else {
                this.warehouseIds = this.warehouseIds.filter((value) => value !== id);
            }
            this.allWarehouses = this.allWarehouseIds.length > 0
                && this.warehouseIds.length === this.allWarehouseIds.length;
            this.syncWarehouseDom();
        },
        syncWarehouseDom() {
            const selected = this.warehouseIds.map(String);
            this.$nextTick(() => {
                this.$el.querySelectorAll('input[name="warehouse_ids[]"]').forEach((input) => {
                    input.checked = selected.includes(String(input.value));
                });
            });
        }
    };
}
</script>
@endpush
@endonce

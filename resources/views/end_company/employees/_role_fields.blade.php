@php
    $rolePermissions = $role?->normalizedPermissions() ?? \App\Models\EndCompanyEmployeeRole::normalizePermissions([]);
    $orderOptions = config('end_company_staff.orders');
    $checkboxBox = 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]';
@endphp

<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Название роли</label>
    <input type="text" name="name" required maxlength="255" value="{{ old('name', $role->name ?? '') }}"
        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25">
</div>
<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Заказы</label>
    @include('manufacturer.products._flat_select', [
        'name' => 'permissions[orders]',
        'placeholder' => 'Нет доступа',
        'options' => $orderOptions,
        'selected' => $rolePermissions['orders'] ?? 'none',
    ])
</div>
<div class="flex flex-col gap-2.5">
    <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
        <input type="checkbox" name="permissions[own_orders_only]" value="1" @checked($rolePermissions['own_orders_only'] ?? false) class="sr-only peer">
        <span class="{{ $checkboxBox }}">
            <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        Только свои заказы
    </label>
    <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
        <input type="checkbox" name="permissions[catalog]" value="1" @checked($rolePermissions['catalog'] ?? false) class="sr-only peer">
        <span class="{{ $checkboxBox }}">
            <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        Каталог товаров
    </label>
    <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
        <input type="checkbox" name="permissions[company_profile]" value="1" @checked($rolePermissions['company_profile'] ?? false) class="sr-only peer">
        <span class="{{ $checkboxBox }}">
            <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        Профиль компании
    </label>
    <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none">
        <input type="checkbox" name="permissions[employees]" value="1" @checked($rolePermissions['employees'] ?? false) class="sr-only peer">
        <span class="{{ $checkboxBox }}">
            <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        Управление сотрудниками
    </label>
</div>

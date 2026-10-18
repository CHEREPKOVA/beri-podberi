@php
    $rolePermissions = $role?->normalizedPermissions() ?? \App\Models\EndCompanyEmployeeRole::normalizePermissions([]);
    $orderOptions = config('end_company_staff.orders');
@endphp

<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Название роли</label>
    <input type="text" name="name" required maxlength="255" value="{{ old('name', $role->name ?? '') }}"
        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
</div>
<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Заказы</label>
    <select name="permissions[orders]" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
        @foreach($orderOptions as $value => $label)
            <option value="{{ $value }}" @selected(($rolePermissions['orders'] ?? 'none') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[own_orders_only]" value="1" @checked($rolePermissions['own_orders_only'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Только свои заказы
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[catalog]" value="1" @checked($rolePermissions['catalog'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Каталог товаров
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[company_profile]" value="1" @checked($rolePermissions['company_profile'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Профиль компании
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[employees]" value="1" @checked($rolePermissions['employees'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Управление сотрудниками
</label>

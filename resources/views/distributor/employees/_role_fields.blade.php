@php
    $rolePermissions = $role?->normalizedPermissions() ?? \App\Models\DistributorEmployeeRole::normalizePermissions([]);
    $orderOptions = config('distributor_staff.orders');
    $purchaseOptions = config('distributor_staff.purchases');
    $productOptions = config('distributor_staff.products');
@endphp

<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Название роли</label>
    <input type="text" name="name" required maxlength="255" value="{{ old('name', $role->name ?? '') }}"
        class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Заказы</label>
        <select name="permissions[orders]" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
            @foreach($orderOptions as $value => $label)
                <option value="{{ $value }}" @selected(($rolePermissions['orders'] ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Покупки</label>
        <select name="permissions[purchases]" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
            @foreach($purchaseOptions as $value => $label)
                <option value="{{ $value }}" @selected(($rolePermissions['purchases'] ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Номенклатура</label>
        <select name="permissions[products]" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">
            @foreach($productOptions as $value => $label)
                <option value="{{ $value }}" @selected(($rolePermissions['products'] ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[prices]" value="1" @checked($rolePermissions['prices'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Изменение цен
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[warehouses]" value="1" @checked($rolePermissions['warehouses'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Склады и остатки
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[employees]" value="1" @checked($rolePermissions['employees'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Управление сотрудниками
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[discounts]" value="1" @checked($rolePermissions['discounts'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Модуль скидок
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[import]" value="1" @checked($rolePermissions['import'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Импорт данных
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[manufacturer_chat]" value="1" @checked($rolePermissions['manufacturer_chat'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Переписка с производителем
</label>
<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
    <input type="checkbox" name="permissions[company_profile]" value="1" @checked($rolePermissions['company_profile'] ?? false) class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
    Профиль компании
</label>

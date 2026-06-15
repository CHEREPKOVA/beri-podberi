@php
    $cardRole = $cardRole ?? 'end_company';
@endphp

<div class="flex flex-wrap items-center gap-2">
    @if($cardRole === 'manufacturer')
        <a href="{{ route('manufacturer.products.edit', ['product' => $product, 'tab' => 'analogs']) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            Управлять аналогами
        </a>
        <a href="{{ route('manufacturer.products.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            Редактировать характеристики
        </a>
        <a href="{{ route('manufacturer.products.edit', ['product' => $product, 'tab' => 'prices']) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Управление ценами/остатками
        </a>
    @elseif($cardRole === 'admin')
        <a href="{{ route('admin.catalog.analogs.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-[#c3242a] hover:text-[#c3242a]">
            Аналоги
        </a>
        <a href="{{ route('admin.catalog.products.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Редактировать карточку
        </a>
    @elseif($cardRole === 'distributor' && ($distributorProduct ?? null))
        <a href="{{ route('distributor.products.show', $distributorProduct) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Редактировать цену и остатки
        </a>
    @endif
</div>

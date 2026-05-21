@extends('layouts.app')

@section('title', 'Склады')
@section('heading', 'Склады')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    @include('manufacturer.profile._warehouses', ['profile' => $profile, 'regions' => $regions])

    <div x-data="manualStocksTable()" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ручное обновление остатков</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Выберите склад, найдите товар и укажите новое количество. Изменения применяются сразу.</p>
        </div>

        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ route('manufacturer.warehouses.index') }}" class="flex flex-col md:flex-row gap-3 md:items-center">
                <div class="relative md:w-72">
                    <select name="stock_warehouse_id" onchange="this.form.submit()" class="w-full appearance-none pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm cursor-pointer">
                        @foreach($profile->warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ $selectedWarehouseId === $warehouse->id ? 'selected' : '' }}>
                                {{ $warehouse->name }}{{ $warehouse->region?->name ? ' (' . $warehouse->region->name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                <div class="relative md:w-96">
                    <input type="text" x-model.debounce.150ms="search" placeholder="Поиск по названию или артикулу"
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Товар</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Артикул</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Текущий остаток</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Последнее ручное изменение</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Новое количество</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($stockItems as $stock)
                        <tr x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower(($stock->product?->name ?? '') . ' ' . ($stock->product?->sku ?? '')) }}">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ $stock->product?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $stock->product?->sku ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm font-medium {{ $stock->quantity > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $stock->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if($stock->stock_updated_at)
                                    {{ $stock->stock_updated_at->format('d.m.Y H:i') }}
                                    @if($stock->updatedByUser)
                                        <span class="block text-xs text-gray-400">{{ $stock->updatedByUser->name }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('manufacturer.warehouses.stocks.update') }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="product_stock_id" value="{{ $stock->id }}">
                                    <input type="number" name="quantity" min="0" value="{{ $stock->quantity }}"
                                        class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                                    <button type="submit" class="px-3 py-2 bg-[#c3242a] text-white text-sm rounded-lg hover:bg-[#a01e24]">
                                        Сохранить
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Товары с остатками не найдены для выбранного склада.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($stockItems->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $stockItems->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function manualStocksTable() {
    return {
        search: '',
        matches(value) {
            if (!this.search) return true;
            return value.includes(this.search.toLowerCase().trim());
        }
    };
}
</script>
@endpush
@endsection

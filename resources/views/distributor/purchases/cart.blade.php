@extends('layouts.app')

@section('title', 'Корзина закупок')
@section('heading', 'Корзина закупок')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-700 dark:bg-green-900/20 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('buyer.catalog.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← В каталог</a>
        <a href="{{ route('distributor.purchases.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">Мои покупки</a>
    </div>

    @if($totals['items_count'] === 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
            <p class="text-gray-600 dark:text-gray-300 mb-4">Корзина закупок пуста</p>
            <a href="{{ route('buyer.catalog.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
                Перейти в каталог производителей
            </a>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Будет создано заказов: <span class="font-semibold text-gray-900 dark:text-white">{{ $totals['groups_count'] }}</span>
                · позиций: <span class="font-semibold">{{ $totals['items_count'] }}</span>
                · сумма: <span class="font-semibold text-[#c3242a]">{{ number_format($totals['total_amount'], 2, ',', ' ') }} ₽</span>
            </p>
            <p class="mt-1 text-xs text-gray-500">Каждый производитель оформляется отдельным заказом.</p>
        </div>

        @foreach($groups as $group)
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                    <div class="flex items-center gap-3">
                        @if($group['manufacturer_logo_url'] ?? null)
                            <img src="{{ $group['manufacturer_logo_url'] }}" alt="" class="h-8 w-8 rounded object-contain bg-white">
                        @endif
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $group['manufacturer_name'] }}</h2>
                            <p class="text-sm text-gray-500">{{ $group['items_count'] }} поз. · {{ number_format($group['subtotal'], 2, ',', ' ') }} ₽</p>
                        </div>
                    </div>
                    @if(!$group['has_blocking_warnings'])
                        <a href="{{ route('distributor.purchases.checkout.create', $group['manufacturer_profile_id']) }}"
                           class="inline-flex items-center h-10 px-4 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
                            Оформить заказ
                        </a>
                    @else
                        <span class="text-sm text-[#c3242a]">Исправьте ошибки перед оформлением</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="text-left py-3 px-5">Товар</th>
                                <th class="text-left py-3 px-3">Цена</th>
                                <th class="text-left py-3 px-3">Кол-во</th>
                                <th class="text-left py-3 px-3">Сумма</th>
                                <th class="text-left py-3 px-5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['items'] as $item)
                                <tr class="border-b border-gray-100 dark:border-gray-700/60">
                                    <td class="py-3 px-5">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $item['sku'] ?: '—' }} · остаток {{ $item['available_stock'] }}</p>
                                        @foreach($item['warnings'] as $warning)
                                            <p class="text-xs mt-1 {{ $warning['blocking'] ? 'text-[#c3242a]' : 'text-amber-600' }}">{{ $warning['message'] }}</p>
                                        @endforeach
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">{{ number_format($item['unit_price'], 2, ',', ' ') }} ₽</td>
                                    <td class="py-3 px-3">
                                        <form method="POST" action="{{ route('distributor.purchases.cart.items.update', $item['cart_item_id']) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1"
                                                   class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1 text-sm">
                                            <button type="submit" class="text-xs text-[#c3242a] hover:underline">OK</button>
                                        </form>
                                    </td>
                                    <td class="py-3 px-3 font-medium whitespace-nowrap">{{ number_format($item['line_total'], 2, ',', ' ') }} ₽</td>
                                    <td class="py-3 px-5">
                                        <form method="POST" action="{{ route('distributor.purchases.cart.items.destroy', $item['cart_item_id']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-gray-500 hover:text-[#c3242a]">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection

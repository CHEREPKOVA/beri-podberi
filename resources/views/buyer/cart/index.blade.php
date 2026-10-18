@extends('layouts.app')

@section('title', 'Корзина')
@section('heading', 'Корзина')

@section('content')
<div class="space-y-6"
     x-data="buyerCartLive(@js($cartLiveUrl), @js($groups->values()), @js($blockedItems->values()), @js($totals), @js($hasBlockingWarnings))"
     x-init="start()">
    @if(session('success'))
        <div class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-700 dark:bg-green-900/20 dark:text-green-100">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-700 dark:bg-red-900/20 dark:text-red-100">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-show="notification" x-cloak
         class="rounded-lg border px-4 py-3 text-sm"
         :class="notificationType === 'error'
            ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-700 dark:bg-red-900/20 dark:text-red-100'
            : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100'">
        <span x-text="notification"></span>
    </div>

    <template x-if="totals.items_count === 0">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
            <p class="text-gray-600 dark:text-gray-300 mb-4">Корзина пуста</p>
            <a href="{{ route('buyer.catalog.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
                Перейти в каталог
            </a>
        </div>
    </template>

    <template x-if="totals.items_count > 0">
        <div class="flex flex-col xl:flex-row xl:items-start gap-6">
            <div class="min-w-0 flex-1 flex flex-col gap-6">
                <template x-for="group in groups" :key="'group-' + group.group_key">
                    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="group.distributor_name"></h2>
                                <p class="text-sm text-gray-500">
                                    Будущий заказ · <span x-text="group.items_count"></span> поз. · <span x-text="formatMoney(group.subtotal)"></span>
                                </p>
                                <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                                    <p x-show="group.region_name">Регион: <span x-text="group.region_name"></span></p>
                                    <p x-show="group.warehouse_name">Склад отгрузки: <span x-text="group.warehouse_name"></span></p>
                                    <p x-show="!group.warehouse_name">Склад отгрузки: под заказ</p>
                                    <p x-show="group.price_scope_label">Условия цены: <span x-text="group.price_scope_label"></span></p>
                                    <p x-show="group.delivery_method_names && group.delivery_method_names.length">
                                        Способы поставки: <span x-text="(group.delivery_method_names || []).join(', ')"></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="text-left py-3 px-5">Товар</th>
                                        <th class="text-left py-3 px-3">Цена</th>
                                        <th class="text-left py-3 px-3">Кол-во</th>
                                        <th class="text-left py-3 px-3">Сумма</th>
                                        <th class="text-right py-3 px-5">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in group.items" :key="item.id">
                                        <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top">
                                            <td class="py-4 px-5">
                                                <div class="font-medium text-gray-900 dark:text-white" x-text="item.name"></div>
                                                <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                                                    <p>Артикул: <span x-text="item.sku || '—'"></span></p>
                                                    <p>Производитель: <span x-text="item.manufacturer_name"></span></p>
                                                    <p>Поставщик: <span x-text="item.distributor_name"></span></p>
                                                    <p>В наличии: <span x-text="item.available_stock"></span> шт.</p>
                                                </div>
                                                <template x-for="(warning, wIndex) in item.warnings" :key="wIndex">
                                                    <p class="mt-2 text-xs"
                                                       :class="warning.blocking ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-300'"
                                                       x-text="warning.message"></p>
                                                </template>
                                            </td>
                                            <td class="py-4 px-3 text-gray-900 dark:text-white whitespace-nowrap">
                                                <template x-if="item.has_discount">
                                                    <div>
                                                        <span class="block text-xs text-gray-400 line-through" x-text="item.list_unit_price_formatted"></span>
                                                        <span x-text="item.unit_price_formatted"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!item.has_discount">
                                                    <span x-text="item.unit_price_formatted"></span>
                                                </template>
                                            </td>
                                            <td class="py-4 px-3">
                                                <form :action="updateUrl(item.id)" method="POST" class="flex items-center gap-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="number" name="quantity" min="1" :value="item.quantity"
                                                           class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm">
                                                    <button type="submit" class="text-xs text-[#c3242a] hover:underline">Изменить</button>
                                                </form>
                                            </td>
                                            <td class="py-4 px-3 font-medium text-gray-900 dark:text-white whitespace-nowrap" x-text="item.line_total_formatted"></td>
                                            <td class="py-4 px-5">
                                                <div class="flex flex-col items-end gap-2">
                                                    <a x-show="item.product_url" :href="item.product_url" class="text-xs text-gray-600 dark:text-gray-300 hover:text-[#c3242a]">В карточку</a>
                                                    <form :action="destroyUrl(item.id)" method="POST" onsubmit="return confirm('Удалить позицию из корзины?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs text-red-600 hover:underline">Удалить</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </template>

                <template x-if="blockedItems.length > 0">
                    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-red-200 dark:border-red-900/40 overflow-hidden">
                        <div class="px-5 py-4 border-b border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-950/20">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Недоступны к оформлению</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Эти позиции останутся в корзине, пока не будут исправлены или удалены.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="text-left py-3 px-5">Товар</th>
                                        <th class="text-left py-3 px-3">Цена</th>
                                        <th class="text-left py-3 px-3">Кол-во</th>
                                        <th class="text-left py-3 px-3">Сумма</th>
                                        <th class="text-right py-3 px-5">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in blockedItems" :key="'blocked-' + item.id">
                                        <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top">
                                            <td class="py-4 px-5">
                                                <div class="font-medium text-gray-900 dark:text-white" x-text="item.name"></div>
                                                <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                                                    <p>Артикул: <span x-text="item.sku || '—'"></span></p>
                                                    <p>Поставщик: <span x-text="item.distributor_name"></span></p>
                                                    <p x-show="item.region_name">Регион: <span x-text="item.region_name"></span></p>
                                                </div>
                                                <template x-for="(warning, wIndex) in item.warnings" :key="wIndex">
                                                    <p class="mt-2 text-xs"
                                                       :class="warning.blocking ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-300'"
                                                       x-text="warning.message"></p>
                                                </template>
                                            </td>
                                            <td class="py-4 px-3 text-gray-900 dark:text-white whitespace-nowrap" x-text="item.unit_price_formatted"></td>
                                            <td class="py-4 px-3">
                                                <form :action="updateUrl(item.id)" method="POST" class="flex items-center gap-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="number" name="quantity" min="1" :value="item.quantity"
                                                           class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1.5 text-sm">
                                                    <button type="submit" class="text-xs text-[#c3242a] hover:underline">Изменить</button>
                                                </form>
                                            </td>
                                            <td class="py-4 px-3 font-medium text-gray-900 dark:text-white whitespace-nowrap" x-text="item.line_total_formatted"></td>
                                            <td class="py-4 px-5">
                                                <form :action="destroyUrl(item.id)" method="POST" onsubmit="return confirm('Удалить позицию из корзины?')" class="text-right">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-600 hover:underline">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </template>

                <p class="text-sm text-gray-500">
                    Система сама разделит корзину на отдельные заказы по поставщику, региону, складу и ценовым условиям.
                </p>
            </div>

            <aside class="w-full xl:w-80 xl:shrink-0 flex flex-col gap-4 xl:sticky xl:top-6 self-start">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Будет создано заказов: <span class="font-semibold text-gray-900 dark:text-white" x-text="totals.groups_count"></span>
                        · позиций: <span class="font-semibold text-gray-900 dark:text-white" x-text="totals.items_count"></span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Разделение по поставщику, региону, складу и условиям цены.</p>
                    <p x-show="totals.blocked_items_count > 0" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        К оформлению <span x-text="totals.groups_count"></span> зак.,
                        <span x-text="totals.blocked_items_count"></span> поз. останутся в корзине.
                    </p>
                    <p x-show="refreshedAt" class="mt-2 text-[10px] text-gray-400">Обновлено: <span x-text="refreshedAt"></span></p>
                </div>

                <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Суммы по будущим заказам</h2>
                    <ul class="space-y-2">
                        <template x-for="group in groups" :key="'total-' + group.group_key">
                            <li class="flex items-center justify-between text-sm border-b border-gray-100 dark:border-gray-700 pb-2 last:border-0 last:pb-0 gap-3">
                                <span class="text-gray-700 dark:text-gray-300 min-w-0 truncate" x-text="group.sidebar_label || group.distributor_name"></span>
                                <span class="font-medium text-gray-900 dark:text-white whitespace-nowrap" x-text="formatMoney(group.subtotal)"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
                        <span class="text-sm text-gray-500">Общая сумма по корзине</span>
                        <span class="text-xl font-semibold text-[#c3242a] whitespace-nowrap" x-text="formatMoney(totals.total_amount)"></span>
                    </div>
                    <p x-show="totals.discount_amount > 0" class="mt-2 text-xs text-green-700 dark:text-green-300">
                        Экономия по скидкам: <span x-text="formatMoney(totals.discount_amount)"></span>
                    </p>
                    <template x-if="totals.groups_count > 0">
                        <a :href="batchCheckoutUrl"
                           class="mt-4 w-full inline-flex justify-center items-center px-4 py-3 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
                            Оформить заказ
                        </a>
                    </template>
                    <template x-if="totals.groups_count === 0">
                        <button type="button" disabled
                                class="mt-4 w-full inline-flex justify-center items-center px-4 py-3 rounded-lg text-sm font-medium bg-gray-200 text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-400">
                            Оформить заказ
                        </button>
                    </template>
                </section>
            </aside>
        </div>
    </template>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerCartLive', (url, initialGroups, initialBlocked, initialTotals, initialBlocking) => ({
        url,
        groups: initialGroups,
        blockedItems: initialBlocked,
        totals: initialTotals,
        hasBlockingWarnings: initialBlocking,
        refreshedAt: null,
        notification: null,
        notificationType: 'warning',
        timer: null,
        previousWarningCount: 0,

        start() {
            this.previousWarningCount = this.countWarnings(this.groups) + this.countItemWarnings(this.blockedItems);
            if (this.timer) clearInterval(this.timer);
            this.timer = setInterval(() => this.refresh(), 60000);
            this.$cleanup(() => {
                clearInterval(this.timer);
                this.timer = null;
            });
        },

        batchCheckoutUrl: @json(route('buyer.checkout.batch')),

        updateUrl(itemId) {
            return @json(url('/buyer/cart/items')) + '/' + itemId;
        },

        destroyUrl(itemId) {
            return this.updateUrl(itemId);
        },

        formatMoney(value) {
            return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0)) + ' ₽';
        },

        countWarnings(groups) {
            return groups.reduce((sum, group) => {
                return sum + this.countItemWarnings(group.items || []);
            }, 0);
        },

        countItemWarnings(items) {
            return (items || []).reduce((itemSum, item) => itemSum + (item.warnings?.length || 0), 0);
        },

        async refresh() {
            try {
                const response = await fetch(this.url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;

                const payload = await response.json();
                const newWarningCount = this.countWarnings(payload.groups || []) + this.countItemWarnings(payload.blocked_items || []);

                if (newWarningCount > this.previousWarningCount) {
                    this.notification = 'В корзине появились новые предупреждения. Проверьте проблемные позиции.';
                    this.notificationType = payload.has_blocking_warnings ? 'error' : 'warning';
                } else if (payload.has_blocking_warnings && !this.hasBlockingWarnings) {
                    this.notification = 'Некоторые позиции стали недоступны для оформления.';
                    this.notificationType = 'error';
                } else {
                    this.notification = null;
                }

                this.groups = payload.groups;
                this.blockedItems = payload.blocked_items || [];
                this.totals = payload.totals;
                this.hasBlockingWarnings = payload.has_blocking_warnings;
                this.refreshedAt = payload.refreshed_at;
                this.previousWarningCount = newWarningCount;
            } catch (e) {
                // тихо пропускаем до следующего интервала
            }
        },
    }));
});
</script>
@endpush
@endsection

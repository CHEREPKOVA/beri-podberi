@extends('layouts.app')

@section('title', 'Заказ '.$order->order_number)
@section('heading')
    @include('orders._page_heading', ['order' => $order])
@endsection

@section('content')
@php
    use App\Services\Order\OrderStatusWorkflowService;
    $btnPrimary = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-[#c3242a] text-white hover:bg-[#a01e24] hover:border-[#a01e24] shadow-sm shadow-[#c3242a]/20';
    $btnOutline = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-transparent text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/20';
    $btnGhost = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm border-2 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
    $inputBrand = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25';
    $selectBrand = $inputBrand.' appearance-none pr-10 cursor-pointer';
    $inputNumber = $inputBrand.' [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';
    $checkboxBox = 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-white transition-colors pointer-events-none peer-checked:border-[#c3242a] peer-checked:bg-[#c3242a] peer-checked:[&>svg]:opacity-100 peer-focus:ring-2 peer-focus:ring-[#c3242a]/30 dark:border-gray-600 dark:bg-gray-800 dark:peer-checked:border-[#c3242a] dark:peer-checked:bg-[#c3242a]';
    $itemCount = $order->items->count();
    $catalogProductOptions = $catalogProducts->map(fn ($product) => [
        'id' => (int) $product->id,
        'name' => $product->name,
        'sku' => $product->manufacturer_sku ?: $product->internal_sku ?: '',
        'category_id' => (int) ($product->product_category_id ?: 0),
        'category' => $product->category?->name ?: 'Без категории',
        'price' => round((float) $product->retail_price, 2),
        'min_qty' => max(1, (int) $product->min_order_quantity),
    ])->values();
    $catalogCategories = $catalogProductOptions
        ->unique('category_id')
        ->map(fn ($row) => ['id' => $row['category_id'], 'name' => $row['category']])
        ->sortBy('name', SORT_NATURAL)
        ->values();
    $orderOfferIds = $order->items->pluck('distributor_product_id')->filter()->map(fn ($id) => (int) $id)->values();
    $oldExtraItems = collect(old('items', []))
        ->filter(fn ($row) => empty($row['id']) && ! empty($row['distributor_product_id']))
        ->map(function ($row) use ($catalogProductOptions) {
            $product = $catalogProductOptions->firstWhere('id', (int) $row['distributor_product_id']);
            if ($product === null) {
                return null;
            }

            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'quantity' => max(1, (int) ($row['quantity'] ?? $product['min_qty'])),
                'unit_price' => isset($row['unit_price']) && $row['unit_price'] !== ''
                    ? (float) $row['unit_price']
                    : $product['price'],
            ];
        })
        ->filter()
        ->values();
@endphp
<div class="space-y-6" x-data="{
    rejectOpen: false,
    shipOpen: false,
    editOpen: window.location.hash === '#edit' || {{ $oldExtraItems->isNotEmpty() ? 'true' : 'false' }},
    claimOpen: false,
    addProductOpen: false,
    productSearch: '',
    productCategory: '',
    extraItems: {{ Js::from($oldExtraItems) }},
    catalog: {{ Js::from($catalogProductOptions) }},
    categories: {{ Js::from($catalogCategories) }},
    orderOfferIds: {{ Js::from($orderOfferIds) }},
    get takenIds() {
        return new Set([...this.orderOfferIds, ...this.extraItems.map(r => r.id)]);
    },
    get filteredCatalog() {
        const q = this.productSearch.trim().toLowerCase();
        const cat = this.productCategory;
        return this.catalog.filter(p => {
            if (this.takenIds.has(p.id)) return false;
            if (cat !== '' && String(p.category_id) !== String(cat)) return false;
            if (!q) return true;
            return p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q));
        });
    },
    openAddProduct() {
        this.addProductOpen = true;
        this.$nextTick(() => this.$refs.productSearch && this.$refs.productSearch.focus());
    },
    closeAddProduct() {
        this.addProductOpen = false;
        this.productSearch = '';
        this.productCategory = '';
    },
    addProduct(p) {
        if (this.takenIds.has(p.id)) return;
        this.extraItems.push({
            id: p.id,
            name: p.name,
            sku: p.sku,
            quantity: p.min_qty || 1,
            unit_price: p.price
        });
        this.closeAddProduct();
    },
    removeExtra(id) {
        this.extraItems = this.extraItems.filter(r => r.id !== id);
    },
    formatPrice(value) {
        return Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}">
    @if(session('success'))
        <div class="rounded-lg border border-[#c3242a]/30 bg-red-50 px-4 py-3 text-sm text-[#a01e24] dark:border-[#c3242a]/40 dark:bg-red-900/20 dark:text-red-100">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-[#c3242a]/40 bg-red-50 px-4 py-3 text-sm text-[#a01e24] dark:border-[#c3242a]/50 dark:bg-red-900/20 dark:text-red-100">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('manufacturer.orders.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            К заказам
        </a>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('manufacturer.orders.history.export', $order) }}"
               class="text-sm text-gray-600 dark:text-gray-300 hover:text-[#c3242a]">Экспорт истории</a>
            <a href="{{ route('manufacturer.orders.print', $order) }}" target="_blank"
               class="text-sm text-gray-600 dark:text-gray-300 hover:text-[#c3242a]">Печать</a>
        </div>
    </div>

    @include('orders._status_timeline', ['order' => $order, 'progressIndex' => $progressIndex])

    @if($buyerChangesRejection ?? null)
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 border-[#c3242a]/30 p-5 space-y-2">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Покупатель отклонил изменения</h2>
            <p class="text-sm text-gray-500">
                Заказ возвращён вам на доработку
                @if($buyerChangesRejection['created_at'] ?? null)
                    · {{ $buyerChangesRejection['created_at']->format('d.m.Y H:i') }}
                @endif
            </p>
            <div class="rounded-lg bg-red-50/70 dark:bg-red-900/10 border border-[#c3242a]/20 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-[#c3242a] mb-1">Комментарий покупателя</p>
                <p class="text-sm text-gray-900 dark:text-white whitespace-pre-line">
                    {{ $buyerChangesRejection['reason'] ?: ($buyerChangesRejection['comment'] ?: 'Без комментария') }}
                </p>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Подтвердите заказ как есть, измените позиции и снова отправьте на согласование, либо отклоните заказ.
            </p>
        </section>
    @endif

    @if(count($actions))
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Доступные действия</h2>
            <div class="flex flex-wrap gap-2">
                @if(in_array(OrderStatusWorkflowService::ACTION_CONFIRM, $actions, true))
                    <form method="POST" action="{{ route('manufacturer.orders.confirm', $order) }}">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }}">Подтвердить</button>
                    </form>
                @endif

                @if(in_array(OrderStatusWorkflowService::ACTION_REJECT, $actions, true))
                    <button type="button" @click="rejectOpen = true; shipOpen = false; editOpen = false; claimOpen = false" class="{{ $btnOutline }}">Отклонить</button>
                @endif

                @if(in_array(OrderStatusWorkflowService::ACTION_SEND_FOR_APPROVAL, $actions, true))
                    <button type="button" @click="editOpen = true; rejectOpen = false; shipOpen = false; claimOpen = false" class="{{ $btnOutline }}">Изменить заказ</button>
                @endif

                @if(in_array(OrderStatusWorkflowService::ACTION_MARK_IN_WORK, $actions, true))
                    <form method="POST" action="{{ route('manufacturer.orders.mark_in_work', $order) }}">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }}">В работу</button>
                    </form>
                @endif

                @if(in_array(OrderStatusWorkflowService::ACTION_MARK_READY, $actions, true))
                    <form method="POST" action="{{ route('manufacturer.orders.mark_ready', $order) }}">
                        @csrf
                        <button type="submit" class="{{ $btnPrimary }}">Готов к отгрузке</button>
                    </form>
                @endif

                @if(in_array(OrderStatusWorkflowService::ACTION_MARK_SHIPPED, $actions, true))
                    <button type="button" @click="shipOpen = true; rejectOpen = false; editOpen = false; claimOpen = false" class="{{ $btnPrimary }}">Отметить как отгружен</button>
                @endif

                <button type="button" @click="claimOpen = true; rejectOpen = false; shipOpen = false; editOpen = false" class="{{ $btnOutline }}">Претензия</button>
            </div>

            <div x-show="rejectOpen" x-cloak class="border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 dark:bg-red-900/10">
                <form method="POST" action="{{ route('manufacturer.orders.reject', $order) }}" class="space-y-3">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Причина отклонения *</label>
                    <textarea name="rejection_reason" rows="3" required maxlength="2000"
                              class="{{ $inputBrand }}"
                              placeholder="Нет товара / ошибка в цене / невозможна отгрузка...">{{ old('rejection_reason') }}</textarea>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btnPrimary }}">Отклонить заказ</button>
                        <button type="button" @click="rejectOpen = false" class="{{ $btnGhost }}">Отмена</button>
                    </div>
                </form>
            </div>

            <div x-show="shipOpen" x-cloak class="border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 dark:bg-red-900/10">
                <form method="POST" action="{{ route('manufacturer.orders.mark_shipped', $order) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium mb-1">ТТН / трек-номер *</label>
                        <input type="text" name="tracking_number" required value="{{ old('tracking_number') }}"
                               class="{{ $inputBrand }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Дата и время отправки</label>
                        <input type="datetime-local" name="shipped_at" value="{{ old('shipped_at', now()->format('Y-m-d\TH:i')) }}"
                               class="{{ $inputBrand }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Склад отправления</label>
                        <div class="relative">
                            <select name="shipping_from_warehouse" class="{{ $selectBrand }}">
                                <option value="">— не указан —</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->name }}" @selected(old('shipping_from_warehouse') === $warehouse->name)>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Транспортная компания</label>
                        <div class="relative">
                            <select name="transport_company_id" class="{{ $selectBrand }}">
                                <option value="">— не менять / не указано —</option>
                                @foreach($transportCompanies as $company)
                                    <option value="{{ $company->id }}" @selected((string) old('transport_company_id', $order->transport_company_id) === (string) $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btnPrimary }}">Отгрузить</button>
                        <button type="button" @click="shipOpen = false" class="{{ $btnGhost }}">Отмена</button>
                    </div>
                </form>
            </div>

            <div x-show="claimOpen" x-cloak class="border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 dark:bg-red-900/10">
                <form method="POST" action="{{ route('manufacturer.orders.claims.store', $order) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium mb-1">Причина *</label>
                        <div class="relative">
                            <select name="reason" required class="{{ $selectBrand }}">
                                <option value="">— выберите —</option>
                                @foreach($claimReasons as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('reason') === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Описание *</label>
                        <textarea name="description" rows="3" required maxlength="5000"
                                  class="{{ $inputBrand }}"
                                  placeholder="Опишите суть претензии">{{ old('description') }}</textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btnPrimary }}">Зарегистрировать претензию</button>
                        <button type="button" @click="claimOpen = false" class="{{ $btnGhost }}">Отмена</button>
                    </div>
                </form>
            </div>

            <div id="edit" x-show="editOpen" x-cloak class="border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 dark:bg-red-900/10">
                <form method="POST" action="{{ route('manufacturer.orders.send_for_approval', $order) }}" class="space-y-3">
                    @csrf
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Измените количество или цену, удалите позиции или добавьте товары из своей номенклатуры — затем отправьте на согласование покупателю. После отправки заказ перейдёт в статус «Требует согласования».
                    </p>
                    @foreach($order->items as $index => $item)
                        <div class="flex flex-col lg:flex-row lg:items-end gap-3 border-b border-gray-100 dark:border-gray-700 pb-3">
                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white break-words">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">{{ $item->sku ?: '—' }}</p>
                            </div>
                            <div class="w-full lg:w-24 shrink-0">
                                <label class="block text-xs text-gray-500 mb-1">Кол-во</label>
                                <input type="number" min="1" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $item->quantity) }}"
                                       class="{{ $inputNumber }}">
                            </div>
                            <div class="w-full lg:w-32 shrink-0">
                                <label class="block text-xs text-gray-500 mb-1">Цена</label>
                                <input type="number" min="0" step="0.01" name="items[{{ $index }}][unit_price]" value="{{ old('items.'.$index.'.unit_price', $item->unit_price) }}"
                                       class="{{ $inputNumber }}">
                            </div>
                            <div class="w-full lg:w-48 shrink-0">
                                <label class="block text-xs text-gray-500 mb-1">Причина</label>
                                <input type="text" name="items[{{ $index }}][reason]" value="{{ old('items.'.$index.'.reason') }}"
                                       class="{{ $inputBrand }}" placeholder="Необязательно">
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none shrink-0 lg:pb-2">
                                <input type="checkbox" name="items[{{ $index }}][delete]" value="1"
                                       @checked(old('items.'.$index.'.delete'))
                                       class="sr-only peer">
                                <span class="{{ $checkboxBox }}">
                                    <svg class="h-3.5 w-3.5 text-white opacity-0 transition-opacity" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                        <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="currentColor" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                Удалить
                            </label>
                        </div>
                    @endforeach

                    <template x-for="(row, idx) in extraItems" :key="row.id">
                        <div class="flex flex-col lg:flex-row lg:items-end gap-3 border-b border-dashed border-gray-200 dark:border-gray-600 pb-3">
                            <input type="hidden" :name="'items[' + ({{ $itemCount }} + idx) + '][distributor_product_id]'" :value="row.id">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white break-words" x-text="row.name"></p>
                                <p class="text-xs text-gray-500" x-text="row.sku || '—'"></p>
                            </div>
                            <div class="w-full lg:w-24 shrink-0">
                                <label class="block text-xs text-gray-500 mb-1">Кол-во</label>
                                <input type="number" min="1" :name="'items[' + ({{ $itemCount }} + idx) + '][quantity]'" :value="row.quantity" class="{{ $inputNumber }}">
                            </div>
                            <div class="w-full lg:w-32 shrink-0">
                                <label class="block text-xs text-gray-500 mb-1">Цена</label>
                                <input type="number" min="0" step="0.01" :name="'items[' + ({{ $itemCount }} + idx) + '][unit_price]'" :value="row.unit_price" class="{{ $inputNumber }}">
                            </div>
                            <button type="button" @click="removeExtra(row.id)" class="{{ $btnGhost }} h-10 shrink-0">Убрать</button>
                        </div>
                    </template>

                    <button type="button" @click="openAddProduct()" class="{{ $btnGhost }} h-9 text-xs disabled:opacity-50 disabled:cursor-not-allowed" :disabled="catalog.length === 0">
                        Добавить товар
                    </button>
                    <p x-show="catalog.length === 0" x-cloak class="text-xs text-gray-500">В номенклатуре нет доступных товаров для этого дистрибьютора.</p>

                    <div>
                        <label class="block text-sm mb-1">Комментарий к изменениям</label>
                        <textarea name="comment" rows="2" class="{{ $inputBrand }}">{{ old('comment') }}</textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btnPrimary }}">Отправить на согласование</button>
                        <button type="button" @click="editOpen = false" class="{{ $btnGhost }}">Отмена</button>
                    </div>
                </form>
            </div>
        </section>
    @else
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Доступные действия</h2>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="claimOpen = !claimOpen" class="{{ $btnOutline }}">Претензия</button>
            </div>
            <div x-show="claimOpen" x-cloak class="border-2 border-[#c3242a]/30 rounded-lg p-4 bg-red-50/40 dark:bg-red-900/10">
                <form method="POST" action="{{ route('manufacturer.orders.claims.store', $order) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium mb-1">Причина *</label>
                        <div class="relative">
                            <select name="reason" required class="{{ $selectBrand }}">
                                <option value="">— выберите —</option>
                                @foreach($claimReasons as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('reason') === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Описание *</label>
                        <textarea name="description" rows="3" required maxlength="5000"
                                  class="{{ $inputBrand }}">{{ old('description') }}</textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="{{ $btnPrimary }}">Зарегистрировать претензию</button>
                        <button type="button" @click="claimOpen = false" class="{{ $btnGhost }}">Отмена</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    @if($order->claims->isNotEmpty())
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Претензии</h2>
            <ul class="space-y-3">
                @foreach($order->claims as $claim)
                    <li class="border border-gray-100 dark:border-gray-700 rounded-lg p-3 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $claim->reasonLabel() }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $claim->claimStatus?->name ?? ($claimStatuses[$claim->status_slug] ?? ($claim->status_slug ?: '—')) }}
                                </p>
                            </div>
                            <p class="text-[11px] text-gray-400 whitespace-nowrap">
                                {{ $claim->created_at?->format('d.m.Y H:i') }}
                            </p>
                        </div>
                        @if($claim->description)
                            <p class="mt-2 text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $claim->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            @include('orders._items', ['order' => $order, 'show_product_links' => $show_product_links ?? true])
            @include('orders._documents')
            @include('orders._history', ['hide_service' => true, 'action_labels' => $action_labels ?? null])
        </div>

        <div class="space-y-6">
            <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4 text-sm">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ответственный менеджер</h2>
                <p>
                    <span class="text-gray-500">Покупатель:</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $order->endCompanyProfile?->displayName() ?? '—' }}</span>
                </p>
                <p>
                    <span class="text-gray-500">Дистрибьютор:</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $order->distributorProfile?->displayName() ?? '—' }}</span>
                </p>
                <form method="POST" action="{{ route('manufacturer.orders.assign_responsible', $order) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Менеджер</label>
                        <div class="relative">
                            <select name="manufacturer_responsible_contact_id" class="{{ $selectBrand }}">
                                <option value="">— не назначен —</option>
                                @foreach($managers as $manager)
                                    <option value="{{ $manager->id }}" @selected((string) old('manufacturer_responsible_contact_id', $order->manufacturer_responsible_contact_id) === (string) $manager->id)>
                                        {{ $manager->full_name }}@if($manager->position) · {{ $manager->position }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <button type="submit" class="{{ $btnOutline }} h-9 text-xs">Сохранить</button>
                </form>
            </section>

            @include('orders._parties')
            @include('orders._finance')
            @include('orders._logistics')
        </div>
    </div>

    <div x-show="addProductOpen" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @keydown.escape.window="closeAddProduct()"
         @click.self="closeAddProduct()">
        <div class="w-full max-w-3xl max-h-[90vh] flex flex-col rounded-2xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700"
             @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Добавить товар</h3>
                    <p class="mt-1 text-sm text-gray-500">Выберите позицию из своей номенклатуры. После отправки изменений заказ перейдёт в статус «Требует согласования».</p>
                </div>
                <button type="button" @click="closeAddProduct()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1" aria-label="Закрыть">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input type="search" x-ref="productSearch" x-model="productSearch"
                           placeholder="Поиск по названию или артикулу…"
                           class="{{ $inputBrand }} pl-10">
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <div class="relative w-full sm:w-64 shrink-0">
                    <select x-model="productCategory" class="{{ $selectBrand }}">
                        <option value="">Все категории</option>
                        @foreach($catalogCategories as $cat)
                            <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-2 py-2 min-h-[12rem]">
                <template x-if="filteredCatalog.length === 0">
                    <p class="px-4 py-8 text-sm text-center text-gray-500">Ничего не найдено</p>
                </template>
                <template x-for="p in filteredCatalog" :key="p.id">
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white break-words" x-text="p.name"></p>
                            <p class="text-xs text-gray-500">
                                <span x-text="p.sku || 'без артикула'"></span>
                                <span> · </span>
                                <span x-text="p.category"></span>
                            </p>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap" x-text="formatPrice(p.price) + ' ₽'"></p>
                        <button type="button" @click="addProduct(p)" class="{{ $btnOutline }} h-9 text-xs shrink-0">Добавить</button>
                    </div>
                </template>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                <button type="button" @click="closeAddProduct()" class="{{ $btnGhost }}">Отмена</button>
            </div>
        </div>
    </div>
</div>
@endsection

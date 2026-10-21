{{-- Состав заказа: таблица позиций по ТЗ 4.4 / подсветка изменений по ТЗ 4.6 --}}
@php
    $showProductLinks = $show_product_links ?? true;
    $showStock = (bool) ($show_stock ?? false);
    $changesSource = $item_changes
        ?? (($approvalProposal['changes'] ?? null) ?: []);
    $changesById = collect($changesSource)
        ->filter(fn ($change) => isset($change['id']))
        ->keyBy(fn ($change) => (int) $change['id']);
    $deletedChanges = $changesById
        ->filter(fn ($change) => ($change['action'] ?? '') === 'deleted')
        ->values();
    $hasHighlightedChanges = $changesById->isNotEmpty();
@endphp
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Состав заказа</h2>
        @if($hasHighlightedChanges)
            <p class="mt-1 text-xs text-[#c3242a]">Изменённые позиции выделены; показаны прежние и новые значения.</p>
        @endif
        @if(filled($order->buyer_comment))
            <div class="mt-2 rounded-lg bg-gray-50 dark:bg-gray-900/40 px-3 py-2">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Комментарий покупателя</p>
                <p class="text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $order->buyer_comment }}</p>
            </div>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table @class(['w-full text-sm', 'min-w-[60rem]' => $showStock, 'min-w-[52rem]' => ! $showStock])>
            <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                <tr>
                    <th class="text-left py-3 px-5 font-medium min-w-[16rem]">Товар</th>
                    <th class="text-right py-3 px-3 font-medium whitespace-nowrap">Цена</th>
                    <th class="text-right py-3 px-3 font-medium whitespace-nowrap">Кол-во</th>
                    @if($showStock)
                        <th class="text-right py-3 px-3 font-medium whitespace-nowrap">Остаток</th>
                    @endif
                    <th class="text-right py-3 px-3 font-medium whitespace-nowrap">Скидка</th>
                    <th class="text-right py-3 pl-3 pr-5 font-medium whitespace-nowrap min-w-[9.5rem]">Сумма</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    @php
                        $thumb = $item->thumbnailUrl();
                        $catalogUrl = $showProductLinks ? $item->catalogUrl() : null;
                        $multiplicity = $item->multiplicityLabel();
                        $discount = $item->discountAmount();
                        $change = $changesById->get((int) $item->id);
                        $isChanged = $change !== null && ($change['action'] ?? '') !== 'deleted';
                        $action = $change['action'] ?? null;
                        $qtyChanged = $isChanged
                            && (int) ($change['old_quantity'] ?? $item->quantity) !== (int) ($change['new_quantity'] ?? $item->quantity);
                        $priceChanged = $isChanged
                            && abs((float) ($change['old_unit_price'] ?? $item->unit_price) - (float) ($change['new_unit_price'] ?? $item->unit_price)) > 0.009;
                        $replaced = $isChanged && $action === 'replaced';
                        $added = $isChanged && $action === 'added';
                        $warehouseChanged = $isChanged
                            && (string) ($change['old_warehouse_id'] ?? '') !== (string) ($change['new_warehouse_id'] ?? '')
                            && array_key_exists('old_warehouse_id', $change ?? []);

                        $availableStock = null;
                        if ($showStock) {
                            if ($item->relationLoaded('distributorProduct') && $item->distributorProduct) {
                                $stocks = $item->distributorProduct->relationLoaded('stocks')
                                    ? $item->distributorProduct->stocks
                                    : collect();
                                if ($item->distributor_warehouse_id) {
                                    $row = $stocks->firstWhere('distributor_warehouse_id', (int) $item->distributor_warehouse_id);
                                    $availableStock = $row
                                        ? max(0, (int) $row->quantity - (int) $row->reserved)
                                        : 0;
                                } else {
                                    $availableStock = (int) $stocks->sum(
                                        fn ($s) => max(0, (int) $s->quantity - (int) $s->reserved)
                                    );
                                }
                            } elseif ($item->relationLoaded('product') && $item->product) {
                                $availableStock = (int) $item->product->available_stock;
                            }
                        }
                    @endphp
                    <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top {{ $isChanged ? 'bg-red-50/50 dark:bg-red-900/15 ring-1 ring-inset ring-[#c3242a]/20' : '' }}">
                        <td class="py-3 px-5 {{ $isChanged ? 'border-l-4 border-l-[#c3242a]' : '' }}">
                            <div class="flex gap-3 items-start min-w-0">
                                @if($thumb)
                                    <img src="{{ $thumb }}" alt="" class="w-12 h-12 rounded object-cover border border-gray-200 dark:border-gray-600 shrink-0">
                                @endif
                                <div class="min-w-0">
                                    @if($replaced && !empty($change['old_name']))
                                        <p class="font-medium text-gray-900 dark:text-white break-words">
                                            <span class="line-through text-gray-400">{{ $change['old_name'] }}</span>
                                            <span class="ml-1">{{ $item->name }}</span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5 break-words">
                                            <span class="line-through">{{ $change['old_sku'] ?: '—' }}</span>
                                            <span class="ml-1">Артикул: {{ $item->sku ?: '—' }}</span>
                                        </p>
                                        <p class="text-xs text-[#c3242a] mt-1">Замена на аналог</p>
                                    @else
                                        @if($catalogUrl)
                                            <a href="{{ $catalogUrl }}" class="font-medium text-gray-900 dark:text-white hover:text-[#c3242a] break-words">
                                                {{ $item->name }}
                                            </a>
                                        @else
                                            <p class="font-medium text-gray-900 dark:text-white break-words">{{ $item->name }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500 mt-0.5 break-words">
                                            Артикул: {{ $item->sku ?: '—' }}
                                            @if($item->manufacturer_name)
                                                · {{ $item->manufacturer_name }}
                                            @endif
                                        </p>
                                    @endif
                                    @if($warehouseChanged)
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Склад:
                                            <span class="line-through text-gray-400">{{ $change['old_warehouse_name'] ?: '—' }}</span>
                                            <span class="ml-1 text-[#c3242a]">{{ $change['new_warehouse_name'] ?: ($item->warehouse?->name ?? '—') }}</span>
                                        </p>
                                    @elseif($item->warehouse)
                                        <p class="text-xs text-gray-500 mt-0.5">Склад: {{ $item->warehouse->name }}</p>
                                    @endif
                                    @if($multiplicity)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $multiplicity }}</p>
                                    @endif
                                    @if($added)
                                        <p class="text-xs text-[#c3242a] mt-1">Добавленная позиция</p>
                                    @endif
                                    @if(!empty($change['reason']))
                                        <p class="text-xs text-[#c3242a] mt-1">Причина: {{ $change['reason'] }}</p>
                                    @endif
                                    @if($catalogUrl && ! $replaced)
                                        <a href="{{ $catalogUrl }}" class="inline-block mt-1 text-xs text-[#c3242a] hover:underline">Карточка товара</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap tabular-nums">
                            @if($priceChanged)
                                <span class="block text-xs text-gray-400 line-through">{{ number_format((float) $change['old_unit_price'], 2, ',', ' ') }} ₽</span>
                                <span class="font-medium text-[#c3242a]">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} ₽</span>
                            @else
                                @if($item->hasDiscount())
                                    <span class="block text-xs text-gray-400 line-through">{{ number_format((float) $item->list_unit_price, 2, ',', ' ') }} ₽</span>
                                @endif
                                {{ number_format((float) $item->unit_price, 2, ',', ' ') }} ₽
                            @endif
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap">
                            @if($qtyChanged)
                                <span class="line-through text-gray-400">{{ $change['old_quantity'] }}</span>
                                <span class="ml-1 font-medium text-[#c3242a]">{{ $item->quantity }}</span>
                            @else
                                {{ $item->quantity }}
                            @endif
                        </td>
                        @if($showStock)
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                @if($availableStock === null)
                                    —
                                @else
                                    <span @class([
                                        'font-medium' => $availableStock < (int) $item->quantity,
                                        'text-[#c3242a]' => $availableStock < (int) $item->quantity,
                                        'text-gray-700 dark:text-gray-200' => $availableStock >= (int) $item->quantity,
                                    ])>{{ $availableStock }}</span>
                                @endif
                            </td>
                        @endif
                        <td class="py-3 px-3 text-right whitespace-nowrap">
                            @if($discount > 0)
                                −{{ number_format($discount, 2, ',', ' ') }} ₽
                            @else
                                —
                            @endif
                        </td>
                        <td class="py-3 pl-3 pr-5 text-right font-medium whitespace-nowrap tabular-nums">
                            @if($isChanged && abs((float) ($change['old_line_total'] ?? 0) - (float) $item->line_total) > 0.009)
                                <span class="block text-xs text-gray-400 line-through font-normal">{{ number_format((float) $change['old_line_total'], 2, ',', ' ') }} ₽</span>
                                <span class="block text-[#c3242a]">{{ number_format((float) $item->line_total, 2, ',', ' ') }} ₽</span>
                            @else
                                {{ number_format((float) $item->line_total, 2, ',', ' ') }} ₽
                            @endif
                        </td>
                    </tr>
                @endforeach

                @foreach($deletedChanges as $change)
                    <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top bg-red-50/40 dark:bg-red-900/10">
                        <td class="py-3 px-5 border-l-4 border-l-[#c3242a]">
                            <p class="font-medium text-gray-500 line-through break-words">{{ $change['name'] ?? 'Товар' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Артикул: {{ $change['sku'] ?: '—' }}</p>
                            <p class="text-xs text-[#c3242a] mt-1">Позиция удалена</p>
                            @if(!empty($change['reason']))
                                <p class="text-xs text-[#c3242a] mt-1">Причина: {{ $change['reason'] }}</p>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap tabular-nums text-gray-400 line-through">
                            {{ number_format((float) ($change['old_unit_price'] ?? 0), 2, ',', ' ') }} ₽
                        </td>
                        <td class="py-3 px-3 text-right whitespace-nowrap">
                            <span class="line-through text-gray-400">{{ $change['old_quantity'] ?? 0 }}</span>
                            <span class="ml-1 font-medium text-[#c3242a]">0</span>
                        </td>
                        @if($showStock)
                            <td class="py-3 px-3 text-right text-gray-400">—</td>
                        @endif
                        <td class="py-3 px-3 text-right text-gray-400">—</td>
                        <td class="py-3 pl-3 pr-5 text-right whitespace-nowrap tabular-nums">
                            <span class="block text-gray-400 line-through">{{ number_format((float) ($change['old_line_total'] ?? 0), 2, ',', ' ') }} ₽</span>
                            <span class="block font-medium text-[#c3242a]">0,00 ₽</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

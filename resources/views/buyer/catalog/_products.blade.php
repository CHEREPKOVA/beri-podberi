@php
    $products = $products ?? collect();
    $companyRegionName = $companyRegionName ?? null;
    $companyRegionId = $companyRegionId ?? null;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-4">
        <h3 class="font-medium text-gray-900 dark:text-white">Товары каталога</h3>
        @if($companyRegionName)
            <p class="text-sm text-gray-500">Показаны склады для региона: {{ $companyRegionName }}</p>
        @endif
    </div>

    <div class="p-4">
        @if($products->isEmpty())
            <div class="py-10 text-center text-gray-500">Товары не найдены</div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($products as $product)
                    @php
                        $visibleStocks = $product->visibleStocksForRegion($companyRegionId);
                        $availableTotal = $visibleStocks->sum(fn ($s) => $s->available_quantity);
                    @endphp
                    <a href="{{ route('buyer.catalog.show', $product) }}" class="block rounded-lg border border-gray-200 dark:border-gray-600 hover:border-[#c3242a] overflow-hidden transition-colors">
                        <div class="aspect-square bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            @if($product->primaryImage())
                                <img src="{{ $product->primaryImage()->url }}" alt="" class="w-full h-full object-cover" />
                            @else
                                <span class="text-gray-400 text-sm">Без фото</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">{{ $product->name }}</p>
                            @if((int) (($product->analogs_count ?? 0) + ($product->analog_of_count ?? 0)) > 0)
                                <p class="mt-1 inline-flex items-center gap-1 text-[11px] text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 rounded px-2 py-0.5">
                                    <span>↔</span>
                                    Есть аналоги
                                </p>
                            @endif
                            <p class="text-xs text-gray-500 mt-1">{{ $product->manufacturerProfile?->short_name ?: $product->manufacturerProfile?->full_name }}</p>
                            <p class="text-sm font-semibold text-[#c3242a] mt-1">{{ $product->base_price ? number_format((float) $product->base_price, 0, ',', ' ') . ' ₽' : '—' }}</p>
                            <p class="text-xs mt-1 {{ $availableTotal > 0 ? 'text-green-600' : 'text-amber-600' }}">
                                {{ $availableTotal > 0 ? "В наличии: {$availableTotal}" : 'Под заказ' }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
            @if($products->hasPages())
                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        @endif
    </div>
</div>

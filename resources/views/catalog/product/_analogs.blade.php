@php
    $analogs = $analogs ?? collect();
    $analogShowRoute = $analogShowRoute ?? 'buyer.catalog.show';
    $cardRole = $cardRole ?? 'end_company';
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Аналоги товара</h2>

    @if(($productUnavailable ?? false) && $analogs->isNotEmpty())
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            Товар недоступен. Доступные аналоги:
        </div>
    @endif

    @if($analogs->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($analogs as $analog)
                @php
                    $isEndCompany = in_array($cardRole, ['end_company', 'distributor'], true);
                    $analogAvailable = $isEndCompany ? (int) ($analog->distributor_available_stock ?? 0) : (int) $analog->available_stock;
                    $analogPrice = $isEndCompany ? ($analog->distributor_display_price ?? null) : $analog->base_price;
                    $attributePreview = $analog->relationLoaded('attributeValues')
                        ? $analog->attributeValues->take(3)
                        : $analog->attributeValues()->with('attribute')->limit(3)->get();
                    $canOpen = $cardRole !== 'manufacturer' || $analog->manufacturer_profile_id === $product->manufacturer_profile_id;
                @endphp
                <article class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
                    <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-700">
                        <img src="{{ $analog->primaryImage()?->url ?? asset('images/placeholder-product.svg') }}" alt="" class="w-full h-full object-cover" />
                    </div>
                    <div class="p-3 flex-1 flex flex-col gap-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">{{ $analog->name }}</p>
                        @if($isEndCompany && ($analog->unavailable_in_region ?? false))
                            <span class="text-[10px] text-gray-600 bg-gray-100 dark:bg-gray-700 rounded px-1.5 py-0.5 inline-flex w-fit">Недоступно в вашем регионе</span>
                        @endif
                        <p class="text-xs text-gray-500">Артикул: {{ $analog->sku }}</p>
                        @if($attributePreview->isNotEmpty())
                            <div class="text-[11px] text-gray-600 dark:text-gray-300 line-clamp-2">
                                @foreach($attributePreview as $value)
                                    <span>{{ $value->attribute?->name }}: {{ $value->value }}@if(!$loop->last) · @endif</span>
                                @endforeach
                            </div>
                        @endif
                        @if($isEndCompany && ! ($analog->unavailable_in_region ?? false))
                            <p class="text-sm font-semibold text-[#c3242a] mt-auto">{{ $analogPrice !== null ? number_format((float) $analogPrice, 2, ',', ' ') . ' ₽' : '—' }}</p>
                            <p class="text-[11px] {{ $analogAvailable > 0 ? 'text-green-600' : 'text-amber-600' }}">
                                {{ $analogAvailable > 0 ? "Остаток: {$analogAvailable}" : 'Нет в наличии (под заказ)' }}
                            </p>
                        @endif
                        @if($canOpen)
                            <a href="{{ route($analogShowRoute, $analog) }}" class="inline-flex text-xs text-[#c3242a] hover:text-[#a01e24] mt-1">Перейти в карточку</a>
                        @else
                            <span class="text-xs text-gray-400 mt-1">Недоступно в вашем каталоге</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if(in_array($cardRole, ['end_company', 'distributor'], true))
                Аналоги не найдены для вашего региона или по условиям совместимости.
            @else
                Аналоги не назначены.
            @endif
        </p>
    @endif
</section>

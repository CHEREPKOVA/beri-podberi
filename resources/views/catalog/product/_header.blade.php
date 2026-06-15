@php
    $showAdminMeta = $showAdminMeta ?? false;
    $showBasePrice = $showBasePrice ?? false;
    $cardRole = $cardRole ?? 'end_company';
    $catalogMarks = $product->catalogMarks();
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <span class="px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Артикул: {{ $product->sku }}</span>
                @if($product->manufacturer_sku)
                    <span class="px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Код производителя: {{ $product->manufacturer_sku }}</span>
                @endif
                @foreach($catalogMarks as $mark)
                    <span class="px-2 py-1 rounded-md text-xs font-medium {{ $mark['class'] }}">{{ $mark['label'] }}</span>
                @endforeach
                @if($showAdminMeta)
                    <span class="px-2 py-1 rounded-md {{ $product->statusBadgeClass() }}">{{ $product->statusLabel() }}</span>
                    @unless($product->show_in_catalog)
                        <span class="px-2 py-1 rounded-md bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200">Скрыт из каталога</span>
                    @endunless
                @endif
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Бренд/завод: {{ $product->manufacturerProfile?->short_name ?: $product->manufacturerProfile?->full_name ?: '—' }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Категория: {{ $product->category?->name ?? 'Не назначена' }}
                @if($product->category?->parent)
                    <span class="text-gray-400">/ {{ $product->category->parent->name }}</span>
                @endif
                @if($product->additionalCategories->isNotEmpty())
                    · Подкатегории: {{ $product->additionalCategories->pluck('name')->implode(', ') }}
                @endif
            </p>
        </div>
        <div class="text-right space-y-1">
            @if($showAdminMeta)
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <p>Публикация: {{ $product->show_in_catalog ? 'В каталоге' : 'Скрыт из каталога' }}</p>
                    <p>Последнее обновление: {{ optional($product->updated_at)->format('d.m.Y H:i') ?: '—' }}</p>
                    <p>Добавлен: {{ optional($product->created_at)->format('d.m.Y H:i') ?: '—' }}</p>
                </div>
            @elseif($cardRole === 'end_company')
                <template x-if="live.show_end_company_price && !live.unavailable_in_region && live.is_purchasable">
                    <div>
                        <p class="text-xl font-semibold text-[#c3242a]" x-text="live.display_price_formatted || '—'"></p>
                        <p class="text-xs text-gray-500">от дистрибьютора в вашем регионе</p>
                    </div>
                </template>
                <template x-if="live.unavailable_in_region">
                    <p class="text-sm text-gray-500">Недоступно в регионе</p>
                </template>
            @elseif($showBasePrice && $product->base_price)
                <p class="text-xl font-semibold text-[#c3242a]">{{ number_format((float) $product->base_price, 2, ',', ' ') }} ₽</p>
                <p class="text-xs text-gray-500">Базовая цена производителя</p>
            @endif
            <p x-show="live.refreshed_at" class="text-[10px] text-gray-400" x-cloak>
                Обновлено: <span x-text="live.refreshed_at"></span>
            </p>
        </div>
    </div>
</section>

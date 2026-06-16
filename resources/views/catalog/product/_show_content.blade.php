@php
    $backUrl = $backUrl ?? route('buyer.catalog.index', ['category' => $product->category?->slug]);
    $showActions = $showActions ?? true;
    $livePayload = $livePayload ?? [];
    $liveUrl = $liveUrl ?? null;
    $refreshSeconds = (int) ($refreshSeconds ?? 0);
@endphp

<div class="space-y-6"
     x-data="productCatalogLive(@js($livePayload), @js($liveUrl), {{ $refreshSeconds }})"
     x-init="start()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#c3242a]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ $backLabel ?? 'Назад в каталог' }}
        </a>
        @if($showActions)
            @include('catalog.product._actions')
        @endif
    </div>

    <div x-show="statusMessage" x-cloak class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-100">
        <span x-text="statusMessage"></span>
    </div>

    @if(!empty($qualityIssues))
        <div class="bg-amber-50 border border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg">
            <p class="font-medium mb-1">Замечания по качеству карточки</p>
            <ul class="text-sm list-disc list-inside">
                @foreach($qualityIssues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-show="isUnavailable && @js(in_array($cardRole ?? '', ['end_company', 'distributor'], true))" x-cloak
         class="rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
        Недоступно в вашем регионе. Цена, остатки и заказ недоступны.
    </div>

    @include('catalog.product._header')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1">
            @include('catalog.product._gallery')
        </div>
        <div class="xl:col-span-2">
            @include('catalog.product._characteristics')
        </div>
    </div>

    @include('catalog.product._warehouse_stocks')

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @include('catalog.product._documents')
        @include('catalog.product._logistics')
    </div>

    @include('catalog.product._analogs')
</div>

@include('catalog.product._live_script')

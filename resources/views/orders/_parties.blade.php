{{-- Блок сторон: поставщик и покупатель --}}
@php
    $isPurchase = $order->isDistributorPurchase();
    $supplier = $isPurchase ? $order->manufacturerProfile : $order->distributorProfile;
    $buyer = $isPurchase ? $order->distributorProfile : $order->endCompanyProfile;
    $supplierContact = $supplier?->contacts->firstWhere('is_primary', true) ?? $supplier?->contacts->first();
    $buyerContact = $buyer?->contacts->firstWhere('is_primary', true) ?? $buyer?->contacts->first();
    $addressContact = $order->deliveryAddress?->contact;
    $regions = $supplier?->regions?->pluck('name')->filter()->values() ?? collect();
    $primaryWarehouse = $supplier?->warehouses?->firstWhere('is_active', true);
@endphp
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-5 text-sm">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Участники заказа</h2>

    @if($order->responsibleContact || $order->manufacturerResponsibleContact)
        <div class="space-y-1 pb-3 border-b border-gray-200 dark:border-gray-700">
            @if($order->responsibleContact)
                <p>
                    <span class="text-gray-500">Ответственный менеджер:</span>
                    <span class="text-gray-900 dark:text-white">{{ $order->responsibleContact->full_name }}</span>
                </p>
            @endif
            @if($order->manufacturerResponsibleContact)
                <p>
                    <span class="text-gray-500">Менеджер производителя:</span>
                    <span class="text-gray-900 dark:text-white">{{ $order->manufacturerResponsibleContact->full_name }}</span>
                </p>
            @endif
        </div>
    @endif

    <div class="space-y-2">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $isPurchase ? 'Производитель' : 'Поставщик' }}</p>
        <p class="font-medium text-gray-900 dark:text-white">{{ $supplier?->displayName() ?? '—' }}</p>
        @if($supplierContact)
            <p class="text-gray-700 dark:text-gray-300">{{ $supplierContact->full_name }}@if($supplierContact->position) · {{ $supplierContact->position }}@endif</p>
            <p class="text-gray-600 dark:text-gray-400">
                @if($supplierContact->phone){{ $supplierContact->phone }}@endif
                @if($supplierContact->phone && $supplierContact->email) · @endif
                @if($supplierContact->email){{ $supplierContact->email }}@endif
            </p>
        @elseif($supplier?->user?->email)
            <p class="text-gray-600 dark:text-gray-400">{{ $supplier->user->email }}</p>
        @endif
        @if($regions->isNotEmpty())
            <p><span class="text-gray-500">Регион обслуживания:</span> <span class="text-gray-900 dark:text-white">{{ $regions->implode(', ') }}</span></p>
        @endif
        @if($order->shipping_from_warehouse)
            <p><span class="text-gray-500">Склад отгрузки:</span> <span class="text-gray-900 dark:text-white">{{ $order->shipping_from_warehouse }}</span></p>
        @elseif($primaryWarehouse)
            <p><span class="text-gray-500">Склад:</span> <span class="text-gray-900 dark:text-white">{{ $primaryWarehouse->name }}@if($primaryWarehouse->address) · {{ $primaryWarehouse->address }}@endif</span></p>
        @endif
        @if($supplier?->delivery_notes)
            <div>
                <p class="text-gray-500">Особенности поставки:</p>
                <p class="text-gray-900 dark:text-white whitespace-pre-line">{{ $supplier->delivery_notes }}</p>
            </div>
        @endif
    </div>

    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Покупатель</p>
        <p class="font-medium text-gray-900 dark:text-white">{{ $buyer?->displayName() ?? '—' }}</p>
        @if($order->distributorWarehouse)
            <div>
                <p class="text-gray-500">Склад поставки:</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ $order->distributorWarehouse->name }}</p>
                @if($order->distributorWarehouse->address)
                    <p class="text-gray-600 dark:text-gray-300">{{ $order->distributorWarehouse->address }}</p>
                @endif
            </div>
        @elseif($order->deliveryAddress)
            <div>
                <p class="text-gray-500">Склад / адрес получения:</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ $order->deliveryAddress->name }}</p>
                <p class="text-gray-600 dark:text-gray-300">{{ $order->deliveryAddress->address }}</p>
                @if($order->deliveryAddress->region)
                    <p class="text-xs text-gray-500">{{ $order->deliveryAddress->region->name }}</p>
                @endif
            </div>
        @endif
        @php
            $responsibleName = $order->deliveryAddress?->contact_person
                ?: $addressContact?->full_name
                ?: $buyerContact?->full_name;
            $responsiblePhone = $order->deliveryAddress?->phone
                ?: $addressContact?->phone
                ?: $buyerContact?->phone;
            $responsibleEmail = $addressContact?->email ?: $buyerContact?->email ?: $buyer?->user?->email;
        @endphp
        @if($responsibleName || $responsiblePhone || $responsibleEmail)
            <div>
                <p class="text-gray-500">Ответственное лицо:</p>
                @if($responsibleName)
                    <p class="text-gray-900 dark:text-white">{{ $responsibleName }}</p>
                @endif
                <p class="text-gray-600 dark:text-gray-400">
                    @if($responsiblePhone){{ $responsiblePhone }}@endif
                    @if($responsiblePhone && $responsibleEmail) · @endif
                    @if($responsibleEmail){{ $responsibleEmail }}@endif
                </p>
            </div>
        @endif
    </div>
</section>

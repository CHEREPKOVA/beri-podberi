@extends('layouts.app')

@section('title', $company->displayName())
@section('heading', $company->displayName())

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('manufacturer.partners.index', ['type' => 'companies']) }}" class="text-sm text-[#c3242a] hover:underline">← К каталогу компаний</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex flex-col md:flex-row gap-6">
            @if($company->logo_url)
            <img src="{{ $company->logo_url }}" alt="" class="w-24 h-24 object-contain rounded-lg border border-gray-200 dark:border-gray-600" />
            @endif
            <div class="flex-1">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $company->full_name }}</h2>
                <p class="mt-2 text-sm text-gray-500">Информационная карточка конечной компании</p>
                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Тип деятельности</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $company->activity_type ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">ИНН</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $company->inn ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Регионы присутствия</dt>
                        <dd class="text-gray-900 dark:text-white">
                            @php
                                $regionNames = $company->deliveryAddresses->map(fn ($a) => $a->region?->name)->filter()->unique();
                            @endphp
                            {{ $regionNames->isNotEmpty() ? $regionNames->join(', ') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Дата регистрации</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $company->created_at?->format('d.m.Y') }}</dd>
                    </div>
                    @if($company->description)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">О компании</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $company->description }}</dd>
                    </div>
                    @endif
                </dl>
                @php $contact = $company->primaryContact(); @endphp
                @if($contact)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-sm">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $contact->full_name }}</p>
                    @if($contact->phone)<p>Тел.: {{ $contact->phone }}</p>@endif
                    @if($contact->email)<p>Email: {{ $contact->email }}</p>@endif
                </div>
                @endif
            </div>
        </div>
    </div>

    @if($permissions->canViewOrders(auth()->user()) && $orders)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">История заказов</h3>
            <p class="text-sm text-gray-500 mt-1">Общая активность на платформе (без коммерческих деталей)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Номер</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($orders as $order)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium">{{ $order->order_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $order->statusLabel() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Заказов пока нет.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $orders->links('vendor.pagination.tailwind') }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection

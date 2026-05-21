@extends('layouts.app')

@section('title', $distributor->displayName())
@section('heading', $distributor->displayName())

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('manufacturer.partners.index') }}" class="text-sm text-[#c3242a] hover:underline">← К каталогу</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex flex-col md:flex-row gap-6">
            @if($distributor->logo_url)
            <img src="{{ $distributor->logo_url }}" alt="" class="w-24 h-24 object-contain rounded-lg border border-gray-200 dark:border-gray-600" />
            @endif
            <div class="flex-1">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $distributor->full_name }}</h2>
                @if($distributor->short_name)
                <p class="text-sm text-gray-500">{{ $distributor->short_name }}</p>
                @endif
                <p class="mt-2 text-sm">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                        @if($cooperationStatus === 'exclusive') bg-amber-100 text-amber-800
                        @elseif($isPartner) bg-green-100 text-green-800
                        @else bg-gray-100 text-gray-700 @endif">
                        {{ $cooperationLabel }}
                    </span>
                    @if($cooperationStatus === 'exclusive')
                    <span class="ml-2 text-amber-600" title="Эксклюзивный партнёр">★</span>
                    @endif
                </p>
                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Регион</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $distributor->primaryRegion()?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">ИНН</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $distributor->inn ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Юридический адрес</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $distributor->legal_address ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Типы продукции</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $distributor->productCategories->pluck('name')->join(', ') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Дата регистрации</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $distributor->created_at?->format('d.m.Y') }}</dd>
                    </div>
                </dl>
                @php $contact = $distributor->primaryContact(); @endphp
                @if($contact)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-sm">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $contact->full_name }}</p>
                    @if($contact->position)<p class="text-gray-500">{{ $contact->position }}</p>@endif
                    @if($contact->phone)<p>Тел.: {{ $contact->phone }}</p>@endif
                    @if($contact->email)<p>Email: {{ $contact->email }}</p>@endif
                </div>
                @endif
            </div>
            <div class="flex flex-col gap-2 shrink-0">
                @if($permissions->canAddPartner(auth()->user()))
                    @if($isPartner)
                        <span class="px-4 py-2 text-sm text-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-600">В моих дистрибьюторах</span>
                        @if($permissions->canRemovePartner(auth()->user()))
                        <form method="POST" action="{{ route('manufacturer.partners.distributors.remove', $distributor) }}"
                            onsubmit="return confirm('Удалить дистрибьютора из списка «Мои»?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-4 py-2 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50">
                                Удалить из моих
                            </button>
                        </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('manufacturer.partners.distributors.add', $distributor) }}"
                            onsubmit="return confirm('Добавить дистрибьютора в мои?')">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-[#c3242a] text-white text-sm rounded-lg hover:bg-[#a01e24]">
                                Добавить к своим
                            </button>
                        </form>
                    @endif
                @endif
                @if($permissions->canAssignExclusive(auth()->user()))
                <a href="#exclusive" class="w-full px-4 py-2 text-center text-sm border border-amber-400 text-amber-700 rounded-lg hover:bg-amber-50">
                    Эксклюзив
                </a>
                @endif
            </div>
        </div>
    </div>

    @if($exclusiveRegions->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Эксклюзивные регионы</h3>
        <ul class="flex flex-wrap gap-2">
            @foreach($exclusiveRegions as $exclusive)
            <li class="px-3 py-1 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 text-sm rounded-full">
                {{ $exclusive->region?->name }}
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($permissions->canAssignExclusive(auth()->user()) && $availableExclusiveRegions->isNotEmpty())
    <div id="exclusive" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Назначить эксклюзив</h3>
        <p class="text-sm text-gray-500 mb-4">Выберите регионы для эксклюзивного партнёрства</p>
        <form method="POST" action="{{ route('manufacturer.partners.distributors.exclusive', $distributor) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                @foreach($availableExclusiveRegions as $region)
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="region_ids[]" value="{{ $region->id }}" class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                    {{ $region->name }}
                </label>
                @endforeach
            </div>
            <div class="flex gap-3">
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white text-sm rounded-lg hover:bg-[#a01e24]">Назначить</button>
            </div>
        </form>
    </div>
    @endif

    @if($permissions->canViewOrders(auth()->user()) && $orders)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">История заказов</h3>
            <p class="text-sm text-gray-500 mt-1">Все заказы дистрибьютора на платформе</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Номер</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сумма</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($orders as $order)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium">{{ $order->order_number }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($order->amount_visible ?? true)
                                {{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽
                            @else
                                <span class="text-gray-400" title="Скрыто по правилам платформы">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $order->statusLabel() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Заказов пока нет.</td>
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

    @if($history->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">История отношений</h3>
        <ul class="space-y-3">
            @foreach($history as $log)
            <li class="text-sm border-l-2 border-gray-200 dark:border-gray-600 pl-4">
                <p class="text-gray-900 dark:text-white">{{ $log->description }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $log->created_at->format('d.m.Y H:i') }}
                    @if($log->performedByUser) — {{ $log->performedByUser->name }} @endif
                </p>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endsection

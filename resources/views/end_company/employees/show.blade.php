@extends('layouts.app')

@section('title', $employee->name)
@section('heading', 'Карточка сотрудника')

@section('content')
<div class="max-w-4xl space-y-6" x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }}, showDeleteModal: false, deleteFormAction: '{{ route('end_company.employees.destroy', $employee) }}', deleteMessage: {{ json_encode('Удалить сотрудника «'.$employee->name.'»?') }} }">
    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold {{ $status === 'blocked' ? 'text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $employee->name }}</h2>
                <p class="text-sm text-gray-500">{{ $employee->email }}</p>
            </div>
            <div>
                @if($status === 'active')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $statusLabel }}</span>
                @elseif($status === 'pending')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700">{{ $statusLabel }}</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800">{{ $statusLabel }}</span>
                @endif
            </div>
        </div>

        <div x-show="!editing" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Роль</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $roleName }}</p>
            </div>
            <div>
                <p class="text-gray-500">Дата создания</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $addedAt?->format('d.m.Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Последний вход</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $employee->last_login_at?->format('d.m.Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Должность / телефон</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $position !== '' ? $position : '—' }}@if($phone !== '') · {{ $phone }}@endif</p>
            </div>
            <div class="md:col-span-2">
                <p class="text-gray-500">Филиалы</p>
                <p class="font-medium text-gray-900 dark:text-white">
                    @if($allAddresses)
                        Вся компания
                    @elseif(empty($addressIds))
                        Не назначены
                    @else
                        {{ $addresses->whereIn('id', $addressIds)->map(fn ($address) => $address->name ?: $address->address)->join(', ') }}
                    @endif
                </p>
            </div>
            @if($comments !== '')
                <div class="md:col-span-2">
                    <p class="text-gray-500">Комментарии</p>
                    <p class="font-medium text-gray-900 dark:text-white whitespace-pre-line">{{ $comments }}</p>
                </div>
            @endif
            <div class="md:col-span-2">
                <p class="text-gray-500 mb-2">Права доступа</p>
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <li>Заказы: {{ $orderOptions[$permissions['orders']] ?? $permissions['orders'] }}</li>
                    <li>Только свои заказы: {{ $permissions['own_orders_only'] ? 'Да' : 'Нет' }}</li>
                    <li>Каталог: {{ $permissions['catalog'] ? 'Есть' : 'Нет' }}</li>
                    <li>Профиль компании: {{ $permissions['company_profile'] ? 'Есть' : 'Нет' }}</li>
                    <li>Сотрудники: {{ $permissions['employees'] ? 'Есть' : 'Нет' }}</li>
                </ul>
            </div>
        </div>

        <form x-show="editing" x-cloak method="POST" action="{{ route('end_company.employees.update', $employee) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('end_company.employees._form')
            <div class="flex justify-end gap-3">
                <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-gray-600">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Сохранить</button>
            </div>
        </form>

        <div x-show="!editing" class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Журнал действий</h3>
            @if($recentOrders->isEmpty())
                <p class="text-sm text-gray-500">Заказов от этого сотрудника пока нет.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach($recentOrders as $order)
                        <li class="flex items-center justify-between gap-3">
                            <a href="{{ route('buyer.orders.show', $order) }}" class="text-[#c3242a] hover:underline">{{ $order->order_number }}</a>
                            <span class="text-gray-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" x-show="!editing" @click="editing = true" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Редактировать</button>

            @if($status === 'pending')
                <form method="POST" action="{{ route('end_company.employees.resend', $employee) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Повторно отправить приглашение</button>
                </form>
            @else
                <form method="POST" action="{{ route('end_company.employees.reset_password', $employee) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Сбросить пароль</button>
                </form>
            @endif

            @if($status === 'blocked')
                <form method="POST" action="{{ route('end_company.employees.unblock', $employee) }}">
                    @csrf
                    <input type="hidden" name="notify" value="1">
                    <button type="submit" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Разблокировать</button>
                </form>
            @elseif($status === 'active')
                <form method="POST" action="{{ route('end_company.employees.block', $employee) }}">
                    @csrf
                    <input type="hidden" name="notify" value="1">
                    <button type="submit" class="px-4 py-2 bg-white border border-amber-300 text-amber-700 rounded-lg text-sm hover:bg-amber-50">Блокировать</button>
                </form>
            @endif

            @if($canDelete)
                <button type="button" @click="showDeleteModal = true" class="px-4 py-2 bg-white border border-red-300 text-red-700 rounded-lg text-sm hover:bg-red-50">Удалить</button>
            @else
                <span class="px-4 py-2 text-xs text-gray-400 self-center">Удаление недоступно: сотрудник уже участвовал в заказах. Можно только заблокировать.</span>
            @endif

            <a href="{{ route('end_company.employees.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 ml-auto">К списку</a>
        </div>
    </div>

    @include('admin.partials.delete_modal')
</div>
@endsection

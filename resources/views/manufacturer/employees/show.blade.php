@extends('layouts.app')

@section('title', $employee->name)
@section('heading', 'Карточка сотрудника')

@section('content')
<div class="max-w-4xl space-y-6" x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }}, showDeleteModal: false, deleteFormAction: '{{ route('manufacturer.employees.destroy', $employee) }}', deleteMessage: {{ json_encode('Удалить сотрудника «'.$employee->name.'»?') }} }">
    @if(session('success'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-[#c3242a]/30 dark:border-[#c3242a]/40 text-[#a01e24] dark:text-red-100 px-4 py-3 rounded-lg">{{ session('success') }}</div>
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
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $statusLabel }}</span>
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
                <p class="text-gray-500">Последняя активность</p>
                <p class="font-medium text-gray-900 dark:text-white">{{ $employee->last_login_at?->format('d.m.Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Филиалы</p>
                <p class="font-medium text-gray-900 dark:text-white">
                    @if($allWarehouses)
                        Все филиалы
                    @elseif(empty($warehouseIds))
                        Не назначены
                    @else
                        {{ $warehouses->whereIn('id', $warehouseIds)->pluck('name')->join(', ') }}
                    @endif
                </p>
            </div>
            <div class="md:col-span-2">
                <p class="text-gray-500 mb-2">Права роли</p>
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <li>Заказы: {{ $orderOptions[$permissions['orders']] ?? $permissions['orders'] }}</li>
                    <li>Номенклатура: {{ $productOptions[$permissions['products']] ?? $permissions['products'] }}</li>
                    <li>Скидки: {{ $discountOptions[$permissions['discounts'] ?? 'none'] ?? ($permissions['discounts'] ?? 'Нет') }}</li>
                    <li>Склады: {{ $permissions['warehouses'] ? 'Есть' : 'Нет' }}</li>
                    <li>Профиль компании: {{ $permissions['company_profile'] ? 'Есть' : 'Нет' }}</li>
                    <li>Претензии: {{ $permissions['claims'] ? 'Есть' : 'Нет' }}</li>
                </ul>
            </div>
        </div>

        <form x-show="editing" x-cloak method="POST" action="{{ route('manufacturer.employees.update', $employee) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('manufacturer.employees._form')
            <div class="flex justify-end gap-3">
                <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-gray-600">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Сохранить</button>
            </div>
        </form>

        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" x-show="!editing" @click="editing = true" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Изменить данные</button>

            @if($status === 'pending')
                <form method="POST" action="{{ route('manufacturer.employees.resend', $employee) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Повторно отправить приглашение</button>
                </form>
            @else
                <form method="POST" action="{{ route('manufacturer.employees.reset_password', $employee) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Сбросить пароль</button>
                </form>
            @endif

            @if($status === 'blocked')
                <form method="POST" action="{{ route('manufacturer.employees.unblock', $employee) }}">
                    @csrf
                    <label class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                        <input type="hidden" name="notify" value="1">
                        <button type="submit">Разблокировать</button>
                    </label>
                </form>
            @elseif($status === 'active')
                <form method="POST" action="{{ route('manufacturer.employees.block', $employee) }}">
                    @csrf
                    <input type="hidden" name="notify" value="1">
                    <button type="submit" class="px-4 py-2 bg-white border border-amber-300 text-amber-700 rounded-lg text-sm hover:bg-amber-50">Блокировать</button>
                </form>
            @endif

            @if($canDelete)
                <button type="button" @click="showDeleteModal = true" class="px-4 py-2 bg-white border border-red-300 text-red-700 rounded-lg text-sm hover:bg-red-50">Удалить сотрудника</button>
            @else
                <span class="px-4 py-2 text-xs text-gray-400 self-center">Удаление недоступно: сотрудник уже участвовал в операциях. Можно только заблокировать.</span>
            @endif

            <a href="{{ route('manufacturer.employees.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 ml-auto">К списку</a>
        </div>
    </div>

    @include('admin.partials.delete_modal')
</div>
@endsection

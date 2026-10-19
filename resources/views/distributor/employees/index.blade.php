@extends('layouts.app')

@section('title', 'Сотрудники')
@section('heading', 'Сотрудники')

@php
    $currentTab = in_array($tab, ['employees', 'roles'], true) ? $tab : 'employees';
@endphp
@section('content')
<div class="space-y-6"
    x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }"
    @if(session('success'))
        x-init="window.dispatchEvent(new CustomEvent('app-toast', { detail: { message: {{ \Illuminate\Support\Js::from(session('success')) }}, success: true, title: '' } }))"
    @endif
>
    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ route('distributor.employees.index') }}"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $currentTab === 'employees' ? 'border-[#c3242a] text-[#c3242a]' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Список сотрудников</a>
        <a href="{{ route('distributor.employees.index', ['tab' => 'roles']) }}"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $currentTab === 'roles' ? 'border-[#c3242a] text-[#c3242a]' : 'border-transparent text-gray-500 hover:text-gray-700' }}">Роли</a>
    </div>

    @if($currentTab === 'employees')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Сотрудники компании</h2>
                    <p class="text-sm text-gray-500 mt-1">Управление учётными записями внутри компании-дистрибьютора.</p>
                </div>
                <a href="{{ route('distributor.employees.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Добавить сотрудника
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ФИО</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Роль</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Филиалы</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Добавлен</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($employees as $row)
                        @php $user = $row['user']; @endphp
                        <tr
                            @if(! $row['is_admin'])
                                onclick="window.location='{{ route('distributor.employees.show', $user) }}'"
                            @endif
                            class="{{ $row['is_admin'] ? '' : 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50' }} {{ $row['status'] === 'blocked' ? 'bg-gray-50 text-gray-400' : '' }}"
                        >
                            <td class="px-6 py-3.5 font-medium {{ $row['status'] === 'blocked' ? 'text-gray-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $user->name }}
                                @if($row['is_self'])
                                    <span class="ml-1 text-xs text-gray-400">(вы)</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 {{ $row['status'] === 'blocked' ? 'text-gray-400' : 'text-gray-600 dark:text-gray-300' }}">{{ $user->email }}</td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $row['is_admin'] ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' }}">
                                    {{ $row['role_name'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                @if($row['status'] === 'active')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">{{ $row['status_label'] }}</span>
                                @elseif($row['status'] === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ $row['status_label'] }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200">{{ $row['status_label'] }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">{{ $row['warehouse_count'] }}</td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $row['added_at']?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">Сотрудники ещё не добавлены.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Роли компании</h2>
                <p class="text-sm text-gray-500 mt-1">Каждая роль задаёт набор прав. Их можно изменить здесь — права сотрудников обновятся автоматически.</p>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($roles as $role)
                    <form method="POST" action="{{ route('distributor.employees.roles.update', $role) }}" class="p-6 space-y-4">
                        @csrf
                        @method('PUT')
                        @include('distributor.employees._role_fields', ['role' => $role])
                        <div class="flex items-center justify-between gap-3">
                            <button type="button"
                                @click="deleteFormAction = '{{ route('distributor.employees.roles.destroy', $role) }}'; deleteMessage = {{ json_encode('Удалить роль «'.$role->name.'»?') }}; showDeleteModal = true"
                                class="text-sm text-red-600 hover:text-red-700">Удалить</button>
                            <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Сохранить</button>
                        </div>
                    </form>
                @empty
                    <p class="p-6 text-sm text-gray-500">Роли ещё не созданы.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Новая роль</h2>
            <form method="POST" action="{{ route('distributor.employees.roles.store') }}" class="space-y-4">
                @csrf
                @include('distributor.employees._role_fields', ['role' => null])
                <button type="submit" class="w-full px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Создать роль</button>
            </form>
        </div>
    </div>
    @endif

    @include('admin.partials.delete_modal')
</div>
@endsection

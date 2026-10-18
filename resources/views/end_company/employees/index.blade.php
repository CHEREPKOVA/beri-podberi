@extends('layouts.app')

@section('title', 'Сотрудники')
@section('heading', 'Сотрудники')

@section('content')
<div class="space-y-6" x-data="{ tab: '{{ in_array($tab, ['employees', 'roles'], true) ? $tab : 'employees' }}', showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <button type="button" @click="tab = 'employees'"
            :class="tab === 'employees' ? 'border-[#c3242a] text-[#c3242a]' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px">Список сотрудников</button>
        <button type="button" @click="tab = 'roles'"
            :class="tab === 'roles' ? 'border-[#c3242a] text-[#c3242a]' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px">Роли</button>
    </div>

    <div x-show="tab === 'employees'" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Сотрудники компании</h2>
                <p class="text-sm text-gray-500 mt-1">Управление учётными записями внутри конечной компании.</p>
            </div>
            <a href="{{ route('end_company.employees.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Добавить сотрудника
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ФИО</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Создан</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Последний вход</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($employees as $row)
                        @php $user = $row['user']; @endphp
                        <tr
                            @if(! $row['is_admin'])
                                onclick="window.location='{{ route('end_company.employees.show', $user) }}'"
                            @endif
                            class="{{ $row['is_admin'] ? '' : 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50' }} {{ $row['status'] === 'pending' ? 'bg-gray-100 dark:bg-gray-700/40' : '' }} {{ $row['status'] === 'blocked' ? 'opacity-50 bg-gray-50 text-gray-400' : '' }}"
                        >
                            <td class="px-4 py-3 text-sm font-medium {{ $row['status'] === 'blocked' ? 'text-gray-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $user->name }}
                                @if($row['is_self'])
                                    <span class="ml-1 text-xs text-gray-400">(вы)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm {{ $row['status'] === 'blocked' ? 'text-gray-400' : 'text-gray-600 dark:text-gray-300' }}">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $row['is_admin'] ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' }}">
                                    {{ $row['role_name'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($row['status'] === 'active')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">{{ $row['status_label'] }}</span>
                                @elseif($row['status'] === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200">{{ $row['status_label'] }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200">{{ $row['status_label'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">{{ $row['added_at']?->format('d.m.Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">{{ $row['last_login_at']?->format('d.m.Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Сотрудники ещё не добавлены.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="tab === 'roles'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Роли компании</h2>
                <p class="text-sm text-gray-500 mt-1">Можно использовать системные роли или создать свои под задачи компании.</p>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($roles as $role)
                    <form method="POST" action="{{ route('end_company.employees.roles.update', $role) }}" class="p-6 space-y-4">
                        @csrf
                        @method('PUT')
                        @include('end_company.employees._role_fields', ['role' => $role])
                        <div class="flex items-center justify-between gap-3">
                            <button type="button"
                                @click="deleteFormAction = '{{ route('end_company.employees.roles.destroy', $role) }}'; deleteMessage = {{ json_encode('Удалить роль «'.$role->name.'»?') }}; showDeleteModal = true"
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
            <form method="POST" action="{{ route('end_company.employees.roles.store') }}" class="space-y-4">
                @csrf
                @include('end_company.employees._role_fields', ['role' => null])
                <button type="submit" class="w-full px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">Создать роль</button>
            </form>
        </div>
    </div>

    @include('admin.partials.delete_modal')
</div>
@endsection

@extends('layouts.app')

@section('title', 'Администраторы и менеджеры')
@section('heading', 'Администраторы и менеджеры')

@section('content')
<div class="space-y-6" x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
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

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Поиск по имени или email..."
                            class="w-64 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <div class="relative">
                        <select name="role" class="appearance-none pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                            <option value="">Все роли</option>
                            @foreach($roleOptions as $role)
                            <option value="{{ $role->slug }}" {{ request('role') === $role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-medium">
                        Применить
                    </button>
                    @if(request()->has(['search', 'role']))
                    <a href="{{ route('admin.staff.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Сбросить</a>
                    @endif
                </form>
                <a href="{{ route('admin.staff.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium transition-colors shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Добавить сотрудника
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Имя</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Роль</th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($staff as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @foreach($user->roles->whereIn('slug', ['admin', 'manager']) as $role)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $role->slug === 'admin' ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300' }}">
                                {{ $role->name }}
                            </span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3">
                            @if($user->id === $protectedAdminId)
                            <span class="text-gray-400" title="Главный администратор защищён от изменений">—</span>
                            @else
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('admin.staff.edit', $user) }}" class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600" title="Редактировать">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                @if($user->id !== auth()->id())
                                <button type="button"
                                    @click="deleteFormAction = '{{ route('admin.staff.destroy', $user) }}'; deleteMessage = {{ json_encode('Удалить сотрудника «' . $user->name . '» из панели управления?') }}; showDeleteModal = true"
                                    class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                                    title="Удалить">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                                @endif
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Нет сотрудников. Добавьте администратора или менеджера.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($staff->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $staff->links() }}
        </div>
        @endif
    </div>

    {{-- Модальное окно подтверждения удаления --}}
    <div x-show="showDeleteModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление сотрудника</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
            <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false"
                    class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Отмена
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
                    Удалить
                </button>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection

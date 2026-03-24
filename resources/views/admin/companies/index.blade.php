@extends('layouts.app')

@section('title', 'Компании')
@section('heading', 'Управление компаниями')

@section('content')
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Поиск по названию..."
                       class="w-64 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">

                <div class="relative">
                    <select name="type" class="appearance-none pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                        <option value="">Все типы</option>
                        @foreach($companyTypes as $type)
                            <option value="{{ $type->slug }}" {{ request('type') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                <div class="relative">
                    <select name="status" class="appearance-none pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                        <option value="">Все статусы</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Активна</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>На модерации</option>
                        <option value="blocked" {{ request('status') === 'blocked' ? 'selected' : '' }}>Заблокирована</option>
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                <input type="text" name="region" value="{{ request('region') }}"
                       placeholder="Регион..."
                       class="w-48 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm">

                <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-sm rounded-lg">Применить</button>
                @if(request()->hasAny(['search', 'type', 'status', 'region']))
                    <a href="{{ route('admin.companies.index') }}" class="text-sm text-gray-500">Сбросить</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Компания</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Тип</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Регион</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Статус</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Сотрудники</th>
                        <th class="px-4 py-3 w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($companies as $company)
                    @php
                        $companyKey = rtrim(strtr(base64_encode($company->type . '|' . $company->name), '+/', '-_'), '=');
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.companies.show', $companyKey) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-[#c3242a] dark:hover:text-red-400 transition-colors">
                                {{ $company->name }}
                            </a>
                            @if($company->legal_name)
                                <div class="text-xs text-gray-500">{{ $company->legal_name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ config('roles.short_labels.' . $company->type, $company->type) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $company->region ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if($company->status === 'active')
                                <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Активна</span>
                            @elseif($company->status === 'pending')
                                <span class="px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-800">На модерации</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-800">Заблокирована</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                            {{ $company->users_count }} / активных: {{ $company->active_users_count }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end">
                                <a href="{{ route('admin.companies.show', $companyKey) }}"
                                   class="p-1.5 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600"
                                   title="Открыть карточку компании">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Компании не найдены.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($companies->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

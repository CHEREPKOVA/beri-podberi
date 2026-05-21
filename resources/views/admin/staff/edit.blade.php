@extends('layouts.app')

@section('title', 'Редактировать сотрудника')
@section('heading', 'Редактировать сотрудника')

@section('content')
<div class="max-w-7xl space-y-6">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm">
        <p class="text-gray-500 dark:text-gray-400 mb-2">Контроль учётной записи</p>
        <div class="flex flex-wrap gap-4">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Статус:</span>
                @if($staff->is_active)
                <span class="ml-1 font-medium text-green-700 dark:text-green-400">Активен</span>
                @else
                <span class="ml-1 font-medium text-gray-700 dark:text-gray-300">Заблокирован</span>
                @endif
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Последний вход:</span>
                <span class="ml-1 font-medium text-gray-900 dark:text-white">{{ $staff->last_login_at ? $staff->last_login_at->format('d.m.Y H:i') : '—' }}</span>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Блокировку и отзыв доступа выполняйте из списка сотрудников.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('admin.staff.update', $staff) }}" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            @csrf
            @method('PUT')

            <div class="space-y-5 lg:col-span-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Имя <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $staff->name) }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('name') border-red-500 @enderror" />
                    @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email', $staff->email) }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('email') border-red-500 @enderror" />
                    @error('email')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Новый пароль</label>
                    <input type="password" name="password" id="password"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('password') border-red-500 @enderror" />
                    @error('password')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Оставьте пустым, если не меняете пароль. Минимум 8 символов.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Подтверждение пароля</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
                </div>

                <div>
                    <label for="role_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Роль <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="role_id" id="role_id" required
                            class="w-full pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent appearance-none cursor-pointer @error('role_id') border-red-500 @enderror">
                            @foreach($roleOptions as $role)
                            <option value="{{ $role->id }}" {{ old('role_id', $staff->roles->whereIn('slug', ['admin', 'manager', 'analyst'])->first()?->id) == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 flex flex-col text-gray-400 dark:text-gray-500">
                            <svg class="w-4 h-4 -mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </div>
                    @error('role_id')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium transition-colors">
                        Сохранить
                    </button>
                    <a href="{{ route('admin.staff.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        Отмена
                    </a>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-4 space-y-4 lg:col-span-7">
                <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100">Индивидуальные права доступа</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Переопределения применяются поверх роли и вступают в силу сразу после сохранения.</p>
                </div>

                @foreach($permissionGroups as $groupLabel => $permissions)
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $groupLabel }}</p>
                    @foreach($permissions as $permission)
                    @php
                        $selectedMode = old('permission_overrides.'.$permission->id, $permissionOverrides[$permission->id] ?? 'inherit');
                    @endphp
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $permission->name }}</p>
                        @if($permission->description)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $permission->description }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="permission_overrides[{{ $permission->id }}]" value="inherit" class="h-4 w-4 border-gray-300 accent-[#c3242a] focus:ring-[#c3242a]" {{ $selectedMode === 'inherit' ? 'checked' : '' }}>
                                <span>Наследовать</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="permission_overrides[{{ $permission->id }}]" value="allow" class="h-4 w-4 border-gray-300 accent-[#c3242a] focus:ring-[#c3242a]" {{ $selectedMode === 'allow' ? 'checked' : '' }}>
                                <span>Разрешить</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="permission_overrides[{{ $permission->id }}]" value="deny" class="h-4 w-4 border-gray-300 accent-[#c3242a] focus:ring-[#c3242a]" {{ $selectedMode === 'deny' ? 'checked' : '' }}>
                                <span>Запретить</span>
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </form>
    </div>
</div>
@endsection

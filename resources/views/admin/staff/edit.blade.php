@extends('layouts.app')

@section('title', 'Редактировать сотрудника')
@section('heading', 'Редактировать сотрудника')

@section('content')
<div class="max-w-xl">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('admin.staff.update', $staff) }}" class="space-y-5">
            @csrf
            @method('PUT')

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
                        <option value="{{ $role->id }}" {{ old('role_id', $staff->roles->whereIn('slug', ['admin', 'manager'])->first()?->id) == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
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
        </form>
    </div>
</div>
@endsection

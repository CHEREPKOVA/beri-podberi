@extends('layouts.app')

@section('title', 'Добавить компанию')
@section('heading', 'Добавить компанию')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('admin.companies.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Типы компании <span class="text-red-500">*</span></label>
                @php
                    $oldTypes = old('company_types', []);
                    if (! is_array($oldTypes)) {
                        $oldTypes = [];
                    }
                @endphp
                <div class="space-y-2 rounded-lg border border-gray-300 dark:border-gray-600 p-3">
                    @foreach(($companyTypes ?? collect()) as $companyType)
                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                name="company_types[]"
                                value="{{ $companyType->slug }}"
                                {{ in_array($companyType->slug, $oldTypes, true) ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"
                            />
                            <span>{{ $companyType->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('company_types')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                @error('company_types.*')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Можно выбрать несколько ролей сразу.</p>
            </div>

            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Полное наименование <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    name="full_name"
                    id="full_name"
                    value="{{ old('full_name') }}"
                    required
                    maxlength="255"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('full_name') border-red-500 @enderror"
                />
                @error('full_name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="inn" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ИНН <span class="text-red-500">*</span></label>
                <input
                    type="text"
                    name="inn"
                    id="inn"
                    value="{{ old('inn') }}"
                    required
                    maxlength="12"
                    inputmode="numeric"
                    pattern="\d{10,12}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('inn') border-red-500 @enderror"
                />
                @error('inn')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Только цифры, 10-12 знаков.</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email для входа в ЛК <span class="text-red-500">*</span></label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="255"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('email') border-red-500 @enderror"
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Если пользователь с этим email уже существует, компания будет привязана к нему.</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Пароль для входа в ЛК <span class="text-red-500">*</span></label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('password') border-red-500 @enderror"
                />
                @error('password')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Для нового пользователя обязателен (минимум 8 символов). Для существующего email можно оставить пустым.</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Подтверждение пароля <span class="text-red-500">*</span></label>
                <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                />
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium transition-colors">
                    Создать компанию
                </button>
                <a href="{{ route('admin.companies.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                    Отмена
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

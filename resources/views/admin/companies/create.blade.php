@extends('layouts.app')

@section('title', 'Пригласить компанию')
@section('heading', 'Пригласить компанию')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <p class="mb-5 text-sm text-gray-600 dark:text-gray-400">
            На указанный email будет отправлена ссылка для завершения регистрации. Срок действия — 3 суток.
        </p>
        <form method="POST" action="{{ route('admin.companies.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Желаемая роль <span class="text-red-500">*</span></label>
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
                <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название компании <span class="text-red-500">*</span></label>
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
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email получателя <span class="text-red-500">*</span></label>
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
                <p class="mt-1 text-xs text-gray-500">На этот адрес придёт письмо со ссылкой для завершения регистрации.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] font-medium transition-colors">
                    Отправить приглашение
                </button>
                <a href="{{ route('admin.companies.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                    Отмена
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

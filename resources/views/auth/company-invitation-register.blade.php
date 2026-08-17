@extends('layouts.guest')

@section('title', 'Регистрация по приглашению')

@section('content')
<div class="relative z-0 bg-white">
    <div class="relative flex min-h-screen w-full flex-col lg:flex-row">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 py-10">
                <div class="flex max-w-xs flex-col items-center">
                    <img src="{{ asset('images/logo-beri.jpg') }}" alt="Бери-Подбери" class="max-h-24 w-auto object-contain" />
                </div>
                <div class="mt-8">
                    <h1 class="mb-2 text-xl font-semibold text-gray-800">Завершение регистрации</h1>
                    <p class="mb-6 text-sm text-gray-600">
                        Задайте пароль и подтвердите данные компании для доступа в личный кабинет.
                    </p>

                    @if ($errors->any())
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('company-invitation.accept', $token) }}" class="space-y-5">
                        @csrf

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                            <input type="text" value="{{ $invitation->email }}" readonly
                                class="h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600" />
                        </div>

                        <div>
                            <label for="company_name" class="mb-1.5 block text-sm font-medium text-gray-700">
                                Название компании <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="company_name" name="company_name" required maxlength="255"
                                value="{{ old('company_name', $invitation->company_name) }}"
                                class="h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                        </div>

                        <div>
                            <label for="inn" class="mb-1.5 block text-sm font-medium text-gray-700">
                                ИНН <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="inn" name="inn" required maxlength="12" inputmode="numeric"
                                value="{{ old('inn') }}" pattern="\d{10,12}"
                                class="h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">
                                Пароль <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password" name="password" required
                                class="h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                            <p class="mt-1 text-xs text-gray-500">Минимум 8 символов, буквы и цифры.</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700">
                                Подтверждение пароля <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                class="h-11 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                        </div>

                        <button type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-[#c3242a] px-4 py-3 text-sm font-medium text-white shadow transition hover:bg-[#a01e24]">
                            Завершить регистрацию
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="relative hidden h-full min-h-[280px] w-full items-center justify-center bg-[#6b1418] bg-cover bg-center bg-no-repeat lg:flex lg:min-h-screen lg:w-1/2"
            style="background-image: url('{{ asset('images/beri-back.jpg') }}');">
        </div>
    </div>
</div>
@endsection

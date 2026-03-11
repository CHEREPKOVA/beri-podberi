@extends('layouts.guest')

@section('title', 'Вход')

@section('content')
<div class="relative z-0 bg-white">
    <div class="relative flex min-h-screen w-full flex-col lg:flex-row">
        {{-- Левая колонка: форма --}}
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 pb-10">

            <div class="flex max-w-xs flex-col items-center">
                <img src="{{ asset('images/logo-beri.jpg') }}" alt="Бери-Подбери" class="max-h-24 w-auto object-contain" />
            </div>

                <div>
                    <div class="mb-5 sm:mb-8">
                    </div>
                    @if (session('status'))
                        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif
                    <div>
                        <form method="POST" action="{{ url('/login') }}">
                            @csrf
                            <div class="space-y-5">
                                <div>
                                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                                        placeholder="info@example.com" required autofocus
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                                    @error('email')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">
                                        Пароль <span class="text-red-500">*</span>
                                    </label>
                                    <div x-data="{ showPassword: false }" class="relative">
                                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password"
                                            placeholder="Введите пароль" required
                                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-12 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                                        <span @click="showPassword = !showPassword"
                                            class="absolute top-1/2 right-4 z-10 -translate-y-1/2 cursor-pointer text-gray-500">
                                            <svg x-show="!showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z" fill="#98A2B3" />
                                            </svg>
                                            <svg x-show="showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" x-cloak>
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61694 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0064 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z" fill="#98A2B3" />
                                            </svg>
                                        </span>
                                    </div>
                                    @error('password')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="flex cursor-pointer items-center text-sm font-normal text-gray-700 select-none">
                                        <input type="checkbox" name="remember" class="mr-3 h-5 w-5 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                                        Запомнить меня
                                    </label>
                                    <a href="{{ url('/reset-password') }}" class="text-sm text-[#c3242a] hover:text-[#a01e24]">
                                        Забыли пароль?
                                    </a>
                                </div>
                                <div>
                                    <button type="submit"
                                        class="flex w-full items-center justify-center rounded-lg bg-[#c3242a] px-4 py-3 text-sm font-medium text-white shadow transition hover:bg-[#a01e24]">
                                        Войти
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="mt-5">
                            <p class="text-center text-sm font-normal text-gray-700 sm:text-start">
                                Нет аккаунта?
                                <a href="{{ url('/signup') }}" class="text-[#c3242a] hover:text-[#a01e24]">Зарегистрироваться</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative hidden h-full min-h-[280px] w-full items-center justify-center bg-[#6b1418] bg-cover bg-center bg-no-repeat lg:flex lg:min-h-screen lg:w-1/2"
            style="background-image: url('{{ asset('images/beri-back.jpg') }}');">
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
@endsection

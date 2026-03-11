@extends('layouts.guest')

@section('title', 'Восстановление пароля')

@section('content')
<div class="relative z-0 bg-white">
    <div class="relative flex min-h-screen w-full flex-col lg:flex-row">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 pb-10">
                <div class="flex max-w-xs flex-col items-center">
                    <img src="{{ asset('images/logo-beri.jpg') }}" alt="Бери-Подбери" class="max-h-24 w-auto object-contain" />
                </div>
                <div class="mt-8">
                    <h1 class="mb-2 text-xl font-semibold text-gray-800">Забыли пароль?</h1>
                    <p class="mb-6 text-sm text-gray-600">
                        Введите email вашего аккаунта — мы отправим ссылку для сброса пароля.
                    </p>
                    @if (session('status'))
                        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <form method="POST" action="{{ url('/forgot-password') }}">
                        @csrf
                        <div class="space-y-5">
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}"
                                    placeholder="info@example.com" required autofocus
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#c3242a] focus:ring-3 focus:ring-[#c3242a]/20 focus:outline-none" />
                            </div>
                            <div>
                                <button type="submit"
                                    class="flex w-full items-center justify-center rounded-lg bg-[#c3242a] px-4 py-3 text-sm font-medium text-white shadow transition hover:bg-[#a01e24]">
                                    Отправить ссылку для сброса
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="mt-5">
                        <a href="{{ url('/login') }}" class="text-sm text-[#c3242a] hover:text-[#a01e24]">
                            ← Вернуться к входу
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="relative hidden h-full min-h-[280px] w-full items-center justify-center bg-[#6b1418] bg-cover bg-center bg-no-repeat lg:flex lg:min-h-screen lg:w-1/2"
            style="background-image: url('{{ asset('images/beri-back.jpg') }}');">
        </div>
    </div>
</div>
@endsection

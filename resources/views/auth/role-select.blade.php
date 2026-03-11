@extends('layouts.guest')

@section('title', 'Выберите роль для работы')

@section('content')
<div class="relative z-0 bg-white">
    <div class="relative flex min-h-screen w-full flex-col lg:flex-row">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 pb-10">
                <div class="flex max-w-xs flex-col items-center">
                    <img src="{{ asset('images/logo-beri.jpg') }}" alt="Бери-Подбери" class="max-h-24 w-auto object-contain" />
                </div>
                <div class="mt-8">
                    <h1 class="mb-6 text-xl font-semibold text-gray-900">Выберите, в каком качестве вы хотите войти</h1>
                    <form method="POST" action="{{ route('role.store') }}">
                        @csrf
                        <div class="space-y-4">
                            @foreach($roles as $role)
                                @php
                                    $companyName = $role->pivot->company_name ?? null;
                                    $optionLabel = $role->labelWithCompany($companyName);
                                @endphp
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 transition hover:border-[#c3242a]/40 hover:bg-gray-50/50 has-[:checked]:border-[#c3242a] has-[:checked]:ring-2 has-[:checked]:ring-[#c3242a]/20">
                                    <input type="radio" name="role_id" value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'checked' : '' }}
                                        class="mt-1 h-4 w-4 border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
                                    <span class="text-sm font-medium text-gray-900">{{ $optionLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('role_id')<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
                        <div class="mt-6">
                            <button type="submit"
                                class="flex w-full items-center justify-center rounded-lg bg-[#c3242a] px-4 py-3 text-sm font-medium text-white shadow transition hover:bg-[#a01e24]">
                                Продолжить
                            </button>
                        </div>
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

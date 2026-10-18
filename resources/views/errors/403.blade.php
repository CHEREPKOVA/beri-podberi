@extends('layouts.guest')

@section('title', 'Доступ запрещён')

@php
    $rawMessage = trim((string) ($exception->getMessage() ?? ''));
    $genericMessages = ['', 'Forbidden', 'This action is unauthorized.'];
    $description = in_array($rawMessage, $genericMessages, true)
        ? 'Этот раздел недоступен для вашего профиля.'
        : $rawMessage;
    $isAuthenticated = auth()->check();
    $homeUrl = $isAuthenticated ? route('dashboard') : route('login');
    $homeLabel = $isAuthenticated ? 'На главную' : 'Перейти ко входу';
@endphp

@section('content')
<div class="relative z-0 bg-white">
    <div class="relative flex min-h-screen w-full flex-col items-center justify-center px-6">
        <img src="{{ asset('images/logo-beri.jpg') }}" alt="Бери-Подбери" class="mb-8 max-h-24 w-auto object-contain" />
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm">
            <p class="mb-2 text-sm font-medium tracking-wide text-[#c3242a]">403</p>
            <h1 class="mb-3 text-xl font-semibold text-gray-800">Доступ запрещён</h1>
            <p class="mb-6 text-sm text-gray-600">{{ $description }}</p>
            <a href="{{ $homeUrl }}" class="inline-flex rounded-lg bg-[#c3242a] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#a01e24]">
                {{ $homeLabel }}
            </a>
        </div>
    </div>
</div>
@endsection

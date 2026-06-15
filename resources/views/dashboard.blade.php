@extends('layouts.app')

@section('title', 'Личный кабинет')
@section('heading', 'Личный кабинет')

@section('content')
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Добро пожаловать в Бери-Подбери</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-300">Вы вошли как:</span> {{ $roleDisplay }}
        </p>
    </div>

    @if($profileCompletion && ($profileCompletion['percent'] ?? 0) < 100)
        @include('dashboard._profile_onboarding', ['completion' => $profileCompletion])
    @endif
</div>
@endsection

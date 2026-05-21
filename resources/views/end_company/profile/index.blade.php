@extends('layouts.app')

@section('title', 'Профиль организации')
@section('heading', 'Профиль организации')

@section('content')
<div x-data="{ activeTab: '{{ $tab }}' }" class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex flex-wrap -mb-px">
                @php
                    $tabs = [
                        'general' => 'Общая информация',
                        'legal' => 'Юридические реквизиты',
                        'contacts' => 'Контакты',
                        'delivery' => 'Адреса доставки',
                        'documents' => 'Документы',
                        'integration' => 'Интеграции',
                        'history' => 'История изменений',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                <button
                    type="button"
                    @click="activeTab = '{{ $key }}'; window.history.replaceState({}, '', '?tab={{ $key }}')"
                    :class="activeTab === '{{ $key }}'
                        ? 'border-[#c3242a] text-[#c3242a]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-4 sm:px-6 border-b-2 font-medium text-sm transition-colors"
                >
                    {{ $label }}
                </button>
                @endforeach
            </nav>
        </div>

        <div class="p-6">
            <div x-show="activeTab === 'general'" x-cloak>
                @include('end_company.profile._general', ['profile' => $profile])
            </div>
            <div x-show="activeTab === 'legal'" x-cloak>
                @include('end_company.profile._legal', ['profile' => $profile])
            </div>
            <div x-show="activeTab === 'contacts'" x-cloak>
                @include('end_company.profile._contacts', ['profile' => $profile])
            </div>
            <div x-show="activeTab === 'delivery'" x-cloak>
                @include('end_company.profile._delivery_addresses', ['profile' => $profile, 'regions' => $regions])
            </div>
            <div x-show="activeTab === 'documents'" x-cloak>
                @include('end_company.profile._documents', ['profile' => $profile])
            </div>
            <div x-show="activeTab === 'integration'" x-cloak>
                @include('end_company.profile._integration', ['profile' => $profile])
            </div>
            <div x-show="activeTab === 'history'" x-cloak>
                @include('end_company.profile._history', ['changes' => $changes])
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
@endsection

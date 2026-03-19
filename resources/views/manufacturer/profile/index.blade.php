@extends('layouts.app')

@section('title', 'Профиль компании')
@section('heading', 'Профиль компании')

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

    {{-- Табы навигации --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex flex-wrap -mb-px">
                @php
                    $tabs = [
                        'company' => 'Информация о компании',
                        'contacts' => 'Контактные данные',
                        'regions' => 'Регионы присутствия',
                        'delivery' => 'Доставка и логистика',
                        'documents' => 'Документы',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                <button
                    @click="activeTab = '{{ $key }}'; window.history.replaceState({}, '', '?tab={{ $key }}')"
                    :class="activeTab === '{{ $key }}'
                        ? 'border-[#c3242a] text-[#c3242a]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors"
                >
                    {{ $label }}
                </button>
                @endforeach
            </nav>
        </div>

        <div class="p-6">
            {{-- Таб: Информация о компании --}}
            <div x-show="activeTab === 'company'" x-cloak>
                @include('manufacturer.profile._company', ['profile' => $profile])
            </div>

            {{-- Таб: Контактные данные --}}
            <div x-show="activeTab === 'contacts'" x-cloak>
                @include('manufacturer.profile._contacts', ['profile' => $profile])
            </div>

            {{-- Таб: Регионы присутствия --}}
            <div x-show="activeTab === 'regions'" x-cloak>
                @include('manufacturer.profile._regions', ['profile' => $profile, 'regions' => $regions, 'federalDistricts' => $federalDistricts])
            </div>

            {{-- Таб: Доставка и логистика --}}
            <div x-show="activeTab === 'delivery'" x-cloak>
                @include('manufacturer.profile._delivery', ['profile' => $profile, 'deliveryMethods' => $deliveryMethods, 'transportCompanies' => $transportCompanies])
            </div>

            {{-- Таб: Документы --}}
            <div x-show="activeTab === 'documents'" x-cloak>
                @include('manufacturer.profile._documents', ['profile' => $profile])
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

@extends('layouts.app')

@section('title', 'Склады')
@section('heading', 'Склады компании')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-[#c3242a]/30 dark:border-[#c3242a]/40 text-[#a01e24] dark:text-red-100 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    @include('distributor.profile._warehouses', ['profile' => $profile, 'regions' => $regions])
</div>
@endsection

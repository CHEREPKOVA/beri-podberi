@extends('layouts.app')

@section('title', 'Склады')
@section('heading', 'Склады')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    @include('manufacturer.profile._warehouses', ['profile' => $profile, 'regions' => $regions])
</div>
@endsection

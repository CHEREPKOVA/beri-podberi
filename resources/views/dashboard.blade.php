@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Welcome to TailAdmin</h2>
    @php
        $currentRole = auth()->user()->getCurrentRole();
        $rolePivot = auth()->user()->roles->firstWhere('id', $currentRole?->id);
        $companyName = $rolePivot?->pivot?->company_name ?? null;
        $roleDisplay = $currentRole ? $currentRole->labelWithCompany($companyName) : '—';
    @endphp
    <p class="text-gray-600 dark:text-gray-400 mb-2">This is a Tailwind CSS admin dashboard template. All styles are loaded via CDN — no Vite or build step required.</p>
    <p class="text-sm text-gray-500 dark:text-gray-500 mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
        <span class="font-medium text-gray-700 dark:text-gray-300">Вход в качестве:</span> {{ $roleDisplay }}
    </p>
</div>
@endsection

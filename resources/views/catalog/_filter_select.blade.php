@php
    $name = $name ?? '';
    $value = $value ?? '';
    $options = $options ?? [];
    $placeholder = $placeholder ?? 'Любой';
    $minWidth = $minWidth ?? '180px';
@endphp
<div class="relative w-full" style="min-width: {{ $minWidth }}; max-width: 240px;">
    <select name="{{ $name }}"
        class="w-full appearance-none pl-3 pr-9 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors hover:border-[#c3242a] cursor-pointer">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $opt)
            <option value="{{ $opt }}" @selected((string) $value === (string) $opt)>{{ $opt }}</option>
        @endforeach
    </select>
    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>
</div>

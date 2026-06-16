@php
    $name = $name ?? '';
    $value = $value ?? '';
    $options = $options ?? [];
    $placeholder = $placeholder ?? 'Любой';
    $minWidth = $minWidth ?? '180px';
    $autoApply = $autoApply ?? false;
@endphp
<div class="relative w-full" style="min-width: {{ $minWidth }}; max-width: 240px;">
    <select name="{{ $name }}"
        @if($autoApply) @change="$dispatch('catalog-apply-filters')" @endif
        class="w-full appearance-none pl-3 pr-9 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors hover:border-[#c3242a] cursor-pointer">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $optValue => $optLabel)
            @php
                $valueKey = is_int($optValue) ? $optLabel : $optValue;
                $label = is_int($optValue) ? $optLabel : $optLabel;
            @endphp
            <option value="{{ $valueKey }}" @selected((string) $value === (string) $valueKey)>{{ $label }}</option>
        @endforeach
    </select>
    @include('catalog._filter_chevron')
</div>

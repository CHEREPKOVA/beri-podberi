@php
    $name = $name ?? '';
    $value = $value ?? '';
    $options = $options ?? [];
    $placeholder = $placeholder ?? null;
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $id = $id ?? $name;

    if (array_is_list($options) && isset($options[0]) && is_array($options[0]) && array_key_exists('value', $options[0])) {
        $normalized = $options;
    } else {
        $normalized = collect($options)->map(fn ($label, $key) => [
            'value' => (string) $key,
            'label' => (string) $label,
        ])->values()->all();
    }

    $selected = (string) old($name, $value ?? '');
@endphp
<div class="relative w-full">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        class="w-full appearance-none pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent transition-colors hover:border-[#c3242a]/60 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
    >
        @if($placeholder !== null)
            <option value="" @selected($selected === '')>{{ $placeholder }}</option>
        @endif
        @foreach($normalized as $option)
            <option value="{{ $option['value'] }}" @selected($selected === (string) $option['value'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
    <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>
</div>

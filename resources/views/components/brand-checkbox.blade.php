@props([
    'labelClass' => 'inline-flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none',
])
<label class="{{ $labelClass }}">
    <input type="checkbox" {{ $attributes->merge(['class' => 'sr-only peer']) }}>
    <x-brand-checkbox-mark />
    @if($slot->isNotEmpty())
        <span class="min-w-0">{{ $slot }}</span>
    @endif
</label>

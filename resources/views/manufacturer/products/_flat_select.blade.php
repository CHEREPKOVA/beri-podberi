@php
    $name = $name ?? 'field';
    $placeholder = $placeholder ?? 'Выберите...';
    $inputId = $inputId ?? ('flat-picker-' . md5($name));
    $allowClear = $allowClear ?? false;
    $clearLabel = $clearLabel ?? 'Сбросить выбор';
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $wrapperClass = $wrapperClass ?? '';
    $size = $size ?? 'md';

    $buttonClass = $buttonClass ?? match ($size) {
        'sm' => 'w-full flex items-center justify-between gap-2 pl-2 pr-8 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-xs text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer',
        default => 'w-full flex items-center justify-between gap-2 pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer',
    };

    $dropdownClass = $dropdownClass ?? match ($size) {
        'sm' => 'absolute z-40 left-0 mt-1 min-w-full w-max max-w-[min(calc(100vw-2rem),24rem)] max-h-56 overflow-y-auto overflow-x-hidden rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1',
        default => 'absolute z-40 left-0 mt-1 min-w-full w-max max-w-[min(calc(100vw-2rem),36rem)] max-h-72 overflow-y-auto overflow-x-hidden rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1',
    };

    $chevronClass = match ($size) {
        'sm' => 'w-4 h-4 shrink-0 text-gray-400 absolute right-2 top-1/2 -translate-y-1/2 transition-transform pointer-events-none',
        default => 'w-5 h-5 shrink-0 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 transition-transform pointer-events-none',
    };

    $rawOptions = $options ?? [];
    if (is_array($rawOptions) && array_is_list($rawOptions) && isset($rawOptions[0]) && is_array($rawOptions[0]) && array_key_exists('value', $rawOptions[0])) {
        $normalizedOptions = collect($rawOptions)->map(fn ($option) => [
            'value' => (string) ($option['value'] ?? ''),
            'label' => (string) ($option['label'] ?? ''),
        ])->filter(fn ($option) => $option['value'] !== '')->values()->all();
    } else {
        $normalizedOptions = collect($rawOptions)->map(function ($label, $value) {
            return [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        })->filter(fn ($option) => $option['value'] !== '')->values()->all();
    }

    $selected = old($name, $selected ?? '');
    $selected = $selected !== null && $selected !== '' ? (string) $selected : '';

    $pickerConfig = [
        'selected' => $selected,
        'placeholder' => $placeholder,
        'options' => $normalizedOptions,
        'disabled' => $disabled,
    ];
@endphp

@once
@push('scripts')
<script>
window.__flatPickerConfigs = window.__flatPickerConfigs || {};

function flatPickerState(config) {
    return {
        pickerOpen: false,
        selected: config.selected ?? '',
        placeholder: config.placeholder || 'Выберите...',
        options: Array.isArray(config.options) ? config.options : [],
        disabled: Boolean(config.disabled),
        hiddenValue() {
            return this.selected;
        },
        hasSelection() {
            return this.selected !== '';
        },
        buttonLabel() {
            if (!this.hasSelection()) {
                return this.placeholder;
            }
            const item = this.options.find((option) => option.value === this.selected);
            return item ? item.label : this.placeholder;
        },
        isSelected(value) {
            return this.selected === value;
        },
        select(value) {
            this.selected = value;
            this.pickerOpen = false;
        },
        clearSelection() {
            this.selected = '';
            this.pickerOpen = false;
        },
    };
}

function flatPickerById(pickerId) {
    return flatPickerState(window.__flatPickerConfigs[pickerId] || {});
}
</script>
@endpush
@endonce

@push('scripts')
<script>
window.__flatPickerConfigs = window.__flatPickerConfigs || {};
window.__flatPickerConfigs[@json($inputId)] = @json($pickerConfig);
</script>
@endpush

<div
    x-data="flatPickerById(@js($inputId))"
    class="relative {{ $wrapperClass }}"
    @keydown.escape.window="pickerOpen = false"
>
    <input
        type="hidden"
        name="{{ $name }}"
        :value="hiddenValue()"
        @if($required) :required="!hasSelection()" @endif
    />

    <button
        type="button"
        id="{{ $inputId }}"
        @click="if (!disabled) pickerOpen = !pickerOpen"
        :disabled="disabled"
        class="{{ $buttonClass }}"
        :class="[
            pickerOpen ? 'ring-2 ring-[#c3242a] border-transparent' : '',
            disabled ? 'opacity-60 cursor-not-allowed' : '',
        ]"
    >
        <span class="truncate" :class="hasSelection() ? 'text-gray-900 dark:text-white' : 'text-gray-500'" x-text="buttonLabel()"></span>
        <svg class="{{ $chevronClass }}" :class="pickerOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="pickerOpen && !disabled"
        x-cloak
        x-transition
        @click.outside="pickerOpen = false"
        class="{{ $dropdownClass }}"
    >
        @if($allowClear)
        <button
            type="button"
            @click="clearSelection()"
            class="w-full flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-red-50 dark:hover:bg-red-900/20 border-b border-gray-100 dark:border-gray-700"
            :class="!hasSelection() ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-600 dark:text-gray-400'"
        >
            {{ $clearLabel }}
        </button>
        @endif

        @if(empty($normalizedOptions))
        <p class="px-4 py-3 text-sm text-gray-500">Нет доступных вариантов</p>
        @else
            @foreach($normalizedOptions as $option)
            <button
                type="button"
                @click="select(@js($option['value']))"
                class="w-full flex items-start gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                :class="isSelected(@js($option['value'])) ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300'"
            >
                <span class="min-w-0 flex-1 whitespace-normal break-words">{{ $option['label'] }}</span>
            </button>
            @endforeach
        @endif
    </div>
</div>

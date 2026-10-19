@php
    $name = $name ?? 'field';
    $openKey = $openKey ?? $name;
    $valueExpr = $valueExpr ?? 'selected';
    $optionsExpr = $optionsExpr ?? 'options';
    $placeholder = $placeholder ?? 'Выберите';
    $required = $required ?? false;
    $onPick = $onPick ?? null;
    $pickExpr = $onPick
        ? $onPick.'(option.value)'
        : $valueExpr." = option.value; openSelect = ''";
@endphp

<div
    class="relative"
    :class="openSelect === '{{ $openKey }}' ? 'z-50' : 'z-0'"
    @click.outside="if (openSelect === '{{ $openKey }}') openSelect = ''"
>
    <input type="hidden" name="{{ $name }}" :value="{{ $valueExpr }}"
        @if($required) :required="!{{ $valueExpr }}" @endif>
    <button
        type="button"
        @click.stop="openSelect = openSelect === '{{ $openKey }}' ? '' : '{{ $openKey }}'"
        class="relative w-full flex items-center justify-between gap-2 pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer"
        :class="openSelect === '{{ $openKey }}' ? 'ring-2 ring-[#c3242a] border-transparent' : ''"
    >
        <span
            class="truncate"
            :class="{{ $valueExpr }} ? 'text-gray-900 dark:text-white' : 'text-gray-500'"
            x-text="optionLabel({{ $optionsExpr }}, {{ $valueExpr }}, '{{ addslashes($placeholder) }}')"
        ></span>
        <svg class="w-5 h-5 shrink-0 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 transition-transform pointer-events-none"
            :class="openSelect === '{{ $openKey }}' ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div
        x-show="openSelect === '{{ $openKey }}'"
        x-cloak
        class="absolute z-50 left-0 right-0 mt-1 max-h-72 overflow-y-auto overflow-x-hidden rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1"
    >
        <template x-for="option in {{ $optionsExpr }}" :key="'opt-' + option.value">
            <button
                type="button"
                @click.stop="{{ $pickExpr }}"
                class="w-full flex items-start gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                :class="String({{ $valueExpr }}) === String(option.value) ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300'"
            >
                <span class="min-w-0 flex-1 whitespace-normal break-words" x-text="option.label"></span>
            </button>
        </template>
    </div>
</div>

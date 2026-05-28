@props([
    'name',
    'options' => [],
    'selected' => [],
    'placeholder' => 'Выберите…',
    'searchable' => false,
])

@php
    $selectedValues = collect($selected)->map(fn ($v) => (string) $v)->values()->all();
    $optionsList = collect($options)->map(function ($option) {
        if (is_array($option)) {
            return [
                'value' => (string) ($option['value'] ?? $option['id'] ?? ''),
                'label' => (string) ($option['label'] ?? $option['name'] ?? ''),
            ];
        }

        return [
            'value' => (string) $option->id,
            'label' => (string) ($option->name ?? $option->label ?? ''),
        ];
    })->filter(fn ($o) => $o['value'] !== '')->values()->all();
@endphp

<div
    class="relative w-full"
    x-data="{
        open: false,
        search: '',
        selected: @js($selectedValues),
        options: @js($optionsList),
        placeholder: @js($placeholder),
        get filtered() {
            if (!@js($searchable) || !this.search.trim()) {
                return this.options;
            }
            const q = this.search.trim().toLowerCase();
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        toggle(value) {
            const i = this.selected.indexOf(value);
            if (i >= 0) {
                this.selected = this.selected.filter((_, idx) => idx !== i);
            } else {
                this.selected = [...this.selected, value];
            }
        },
        isSelected(value) {
            return this.selected.includes(value);
        },
        summary() {
            if (this.selected.length === 0) {
                return this.placeholder;
            }
            if (this.selected.length === 1) {
                const item = this.options.find(o => o.value === this.selected[0]);
                return item ? item.label : '1 выбрано';
            }
            return 'Выбрано: ' + this.selected.length;
        },
        clear() {
            this.selected = [];
        }
    }"
    @click.away="open = false"
>
    <template x-for="value in selected" :key="value">
        <input type="hidden" name="{{ $name }}" :value="value">
    </template>

    <button
        type="button"
        @click="open = !open"
        class="w-full px-3 py-2.5 pr-9 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent flex items-center justify-between gap-2"
    >
        <span class="truncate" x-text="summary()"></span>
        <svg class="w-4 h-4 shrink-0 text-gray-500 transition-transform pointer-events-none" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute z-30 mt-1 left-0 right-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg overflow-hidden"
    >
        @if($searchable)
        <div class="p-2 border-b border-gray-200 dark:border-gray-700">
            <input
                type="search"
                x-model="search"
                @click.stop
                placeholder="Поиск…"
                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
            />
        </div>
        @endif

        <div class="max-h-56 overflow-y-auto py-1">
            <template x-if="filtered.length === 0">
                <p class="px-3 py-2 text-sm text-gray-500">Ничего не найдено</p>
            </template>
            <template x-for="option in filtered" :key="option.value">
                <label
                    class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm text-gray-700 dark:text-gray-300"
                    @click.prevent="toggle(option.value)"
                >
                    <input
                        type="checkbox"
                        :checked="isSelected(option.value)"
                        class="rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a] shrink-0 pointer-events-none"
                    />
                    <span x-text="option.label"></span>
                </label>
            </template>
        </div>

        <div class="flex items-center justify-between gap-2 px-3 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <button type="button" @click="clear()" class="text-xs text-gray-600 dark:text-gray-400 hover:text-[#c3242a]">
                Сбросить
            </button>
            <button type="button" @click="open = false" class="text-xs font-medium text-[#c3242a] hover:underline">
                Готово
            </button>
        </div>
    </div>
</div>

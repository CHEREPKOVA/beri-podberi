@props([
    'wrapperClass' => '',
    'size' => 'md',
    'searchable' => false,
    'searchPlaceholder' => 'Поиск…',
])
@php
    $buttonClass = match ($size) {
        'sm' => 'relative w-full flex items-center justify-between gap-2 pl-3 pr-8 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed',
        default => 'relative w-full flex items-center justify-between gap-2 pl-3 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed',
    };
    $chevronClass = match ($size) {
        'sm' => 'w-4 h-4 shrink-0 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 transition-transform pointer-events-none',
        default => 'w-4 h-4 shrink-0 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 transition-transform pointer-events-none',
    };
    $dropdownClass = 'absolute z-50 left-0 right-0 mt-1 w-full max-h-60 overflow-y-auto overflow-x-hidden rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1';
    $xRef = $attributes->get('x-ref');
    $selectAttributes = $attributes->except('x-ref');
    $isSearchable = filter_var($searchable, FILTER_VALIDATE_BOOLEAN);
@endphp

@once
@push('scripts')
<script>
window.styledSelectUi = function styledSelectUi(config = {}) {
    return {
        open: false,
        options: [],
        label: '',
        disabled: false,
        invalid: false,
        selectEl: null,
        searchable: Boolean(config.searchable),
        searchPlaceholder: config.searchPlaceholder || 'Поиск…',
        query: '',
        get visibleOptions() {
            const q = (this.query || '').trim().toLowerCase();
            return this.options.filter((item) => {
                if (item.hidden) return false;
                if (!q) return true;
                return (item.label || '').toLowerCase().includes(q);
            });
        },
        initFrom(selectEl) {
            if (!selectEl || selectEl.tagName !== 'SELECT') return;
            this.selectEl = selectEl;
            this.syncFromSelect();
            selectEl.addEventListener('change', () => this.syncFromSelect());
            selectEl.addEventListener('input', () => this.syncFromSelect());
            // Keep UI in sync when Alpine (or other code) toggles disabled/options.
            this._selectObserver = new MutationObserver(() => this.syncFromSelect());
            this._selectObserver.observe(selectEl, {
                attributes: true,
                attributeFilter: ['disabled'],
                childList: true,
                subtree: true,
            });
            // Native <select> is visually hidden; browser HTML5 validation otherwise
            // fails silently ("An invalid form control is not focusable").
            selectEl.addEventListener('invalid', (event) => {
                event.preventDefault();
                this.invalid = true;
                this.$nextTick(() => {
                    this.$refs.trigger?.focus({ preventScroll: true });
                    this.$refs.trigger?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
            });
        },
        syncFromSelect() {
            const el = this.selectEl;
            if (!el) return;
            const seen = new Set();
            this.options = Array.from(el.options).reduce((list, option) => {
                const value = String(option.value);
                if (seen.has(value)) return list;
                seen.add(value);
                list.push({
                    value,
                    label: (option.textContent || '').trim(),
                    disabled: Boolean(option.disabled),
                    hidden: Boolean(option.hidden),
                });
                return list;
            }, []);
            const current = this.options.find((option) => option.value === String(el.value));
            this.label = current ? current.label : '';
            this.disabled = Boolean(el.disabled);
            if (this.invalid && el.checkValidity()) {
                this.invalid = false;
            }
        },
        toggle() {
            if (this.disabled) return;
            this.open = !this.open;
            if (this.open) {
                this.syncFromSelect();
                this.query = '';
                if (this.searchable) {
                    this.$nextTick(() => {
                        this.$nextTick(() => {
                            this.$refs.searchInput?.focus();
                        });
                    });
                }
            } else {
                this.query = '';
            }
        },
        choose(value) {
            if (this.disabled || !this.selectEl) return;
            const next = String(value);
            if (this.selectEl.value !== next) {
                this.selectEl.value = next;
                this.selectEl.dispatchEvent(new Event('input', { bubbles: true }));
                this.selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.syncFromSelect();
            this.query = '';
            this.open = false;
        },
        isSelected(value) {
            return this.selectEl && String(this.selectEl.value) === String(value);
        },
    };
};
</script>
@endpush
@endonce

{{-- Select stays outside nested x-data so parent x-model / x-ref keep working. --}}
<div class="relative {{ $wrapperClass }}" @if($xRef) x-ref="{{ $xRef }}" @endif>
    <select {{ $selectAttributes->merge(['class' => 'hidden']) }} aria-hidden="true" tabindex="-1">
        {{ $slot }}
    </select>

    <div
        x-data="styledSelectUi({ searchable: {{ $isSearchable ? 'true' : 'false' }}, searchPlaceholder: @js($searchPlaceholder) })"
        x-init="initFrom($el.previousElementSibling)"
        class="relative"
        :class="open ? 'z-50' : 'z-0'"
        @keydown.escape.window="open = false; query = ''"
        @click.outside="open = false; query = ''"
    >
        <button
            type="button"
            x-ref="trigger"
            @click.stop="toggle()"
            :disabled="disabled"
            :aria-invalid="invalid ? 'true' : 'false'"
            class="{{ $buttonClass }}"
            :class="invalid
                ? 'border-red-500 ring-2 ring-red-200 dark:ring-red-900/40'
                : (open ? 'ring-2 ring-[#c3242a] border-transparent' : '')"
        >
            <span class="truncate min-w-0 flex-1" x-text="label || '—'">—</span>
            <svg class="{{ $chevronClass }}" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <template x-if="open && !disabled">
            <div class="{{ $dropdownClass }}">
                <template x-if="searchable">
                    <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 px-2 py-2" @click.stop>
                        <input
                            type="search"
                            x-ref="searchInput"
                            x-model="query"
                            @keydown.escape.stop="open = false; query = ''"
                            @keydown.enter.prevent="visibleOptions.length === 1 && !visibleOptions[0].disabled && choose(visibleOptions[0].value)"
                            :placeholder="searchPlaceholder"
                            class="w-full rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 px-2.5 py-1.5 text-sm text-gray-800 dark:text-gray-200 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
                            autocomplete="off"
                        >
                    </div>
                </template>
                <template x-for="option in visibleOptions" :key="option.value">
                    <button
                        type="button"
                        @click.stop="!option.disabled && choose(option.value)"
                        class="w-full flex items-start gap-2 px-3 py-2 text-left text-sm transition-colors"
                        :class="[
                            option.disabled ? 'opacity-50 cursor-not-allowed text-gray-400' : 'hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer',
                            isSelected(option.value) ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300',
                        ]"
                        :disabled="option.disabled"
                        :title="option.label"
                    >
                        <span class="min-w-0 flex-1 break-words whitespace-normal leading-snug" x-text="option.label"></span>
                    </button>
                </template>
                <template x-if="searchable && visibleOptions.length === 0">
                    <p class="px-3 py-2 text-sm text-gray-400">Ничего не найдено</p>
                </template>
            </div>
        </template>

        <p x-show="invalid" x-cloak class="mt-1 text-xs text-red-600">Выберите значение из списка.</p>
    </div>
</div>

@php
    $name = $name ?? 'category_id';
    $multiple = $multiple ?? false;
    $placeholder = $placeholder ?? 'Выберите категорию';
    $tree = $tree ?? collect();
    $inputId = $inputId ?? ('category-picker-' . md5($name . ($multiple ? '-multi' : '-single')));
    $allowClear = $allowClear ?? false;
    $clearLabel = $clearLabel ?? 'Сбросить выбор';
    $notifyCategoryChange = $notifyCategoryChange ?? false;
    $wrapperClass = $wrapperClass ?? '';
    $buttonClass = $buttonClass ?? 'w-full flex items-center justify-between gap-2 pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-left text-sm text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer';
    $dropdownClass = $dropdownClass ?? 'absolute z-40 left-0 mt-1 min-w-full w-max max-w-[min(calc(100vw-2rem),36rem)] max-h-72 overflow-y-auto overflow-x-hidden rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1';

    if ($multiple) {
        $selectedIds = array_values(array_map('intval', old($name, $selectedIds ?? [])));
        $selectedId = null;
    } else {
        $selectedId = old($name, $selectedId ?? null);
        $selectedId = $selectedId !== null && $selectedId !== '' ? (int) $selectedId : null;
        $selectedIds = [];
    }

    $pickerConfig = [
        'multiple' => $multiple,
        'selectedId' => $selectedId,
        'selectedIds' => $selectedIds,
        'placeholder' => $placeholder,
        'labels' => ($categories ?? collect())->pluck('name', 'id')->all(),
        'notifyCategoryChange' => $notifyCategoryChange,
    ];
@endphp

@once
@push('scripts')
<script>
window.__categoryPickerConfigs = window.__categoryPickerConfigs || {};

function categoryPickerState(config) {
    return {
        pickerOpen: false,
        multiple: Boolean(config.multiple),
        selectedId: config.selectedId ?? null,
        selectedIds: Array.isArray(config.selectedIds) ? config.selectedIds : [],
        placeholder: config.placeholder || 'Выберите категорию',
        labels: config.labels || {},
        notifyCategoryChange: Boolean(config.notifyCategoryChange),
        emitCategoryChange() {
            if (!this.notifyCategoryChange || this.multiple) {
                return;
            }
            this.$dispatch('product-main-category-changed', {
                categoryId: this.selectedId,
            });
        },
        hiddenValue() {
            return this.selectedId === null ? '' : this.selectedId;
        },
        hasSelection() {
            if (this.multiple) {
                return this.selectedIds.length !== 0;
            }
            return this.selectedId !== null;
        },
        buttonLabel() {
            if (this.multiple) {
                if (this.selectedIds.length === 0) {
                    return this.placeholder;
                }
                if (this.selectedIds.length === 1) {
                    return this.labels[this.selectedIds[0]] || this.placeholder;
                }
                return 'Выбрано: ' + this.selectedIds.length;
            }
            if (this.selectedId === null) {
                return this.placeholder;
            }
            return this.labels[this.selectedId] || this.placeholder;
        },
        isSelected(id) {
            if (this.multiple) {
                return this.selectedIds.indexOf(id) !== -1;
            }
            return this.selectedId === id;
        },
        select(id) {
            if (this.multiple) {
                if (this.selectedIds.indexOf(id) !== -1) {
                    this.selectedIds = this.selectedIds.filter(function (item) {
                        return item !== id;
                    });
                } else {
                    this.selectedIds = this.selectedIds.concat([id]);
                }
                return;
            }
            this.selectedId = id;
            this.pickerOpen = false;
            this.emitCategoryChange();
        },
        clearSelection() {
            if (this.multiple) {
                this.selectedIds = [];
            } else {
                this.selectedId = null;
            }
            this.pickerOpen = false;
            this.emitCategoryChange();
        },
    };
}

function categoryPickerById(pickerId) {
    return categoryPickerState(window.__categoryPickerConfigs[pickerId] || {});
}
</script>
@endpush
@endonce

@push('scripts')
<script>
window.__categoryPickerConfigs = window.__categoryPickerConfigs || {};
window.__categoryPickerConfigs[@json($inputId)] = @json($pickerConfig);
</script>
@endpush

<div
    x-data="categoryPickerById(@js($inputId))"
    class="relative {{ $wrapperClass }}"
    @keydown.escape.window="pickerOpen = false"
>
    @if($multiple)
        <template x-for="id in selectedIds" :key="id">
            <input type="hidden" name="{{ $name }}[]" :value="id" />
        </template>
    @else
        <input type="hidden" name="{{ $name }}" :value="hiddenValue()" />
    @endif

    <button
        type="button"
        id="{{ $inputId }}"
        @click="pickerOpen = !pickerOpen"
        class="{{ $buttonClass }}"
        :class="pickerOpen ? 'ring-2 ring-[#c3242a] border-transparent' : ''"
    >
        <span class="truncate" :class="hasSelection() ? 'text-gray-900 dark:text-white' : 'text-gray-500'" x-text="buttonLabel()"></span>
        <svg class="w-5 h-5 shrink-0 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 transition-transform pointer-events-none"
            :class="pickerOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="pickerOpen"
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
        @if($tree->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">Нет доступных категорий</p>
        @else
            @include('manufacturer.products._category_tree_select_nodes', ['nodes' => $tree, 'level' => 0, 'multiple' => $multiple])
        @endif
    </div>
</div>

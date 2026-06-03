@php
    $filterDisplayTypes = $filterDisplayTypes ?? \App\Models\ProductAttribute::filterDisplayLabels();
    $filterValuesSources = $filterValuesSources ?? \App\Models\ProductAttribute::filterValuesSourceLabels();
    $optionsRawValue = old('options_raw', isset($attribute) && is_array($attribute->options ?? null) ? implode("\n", $attribute->options) : '');
    $optionsItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $optionsRawValue))));
    if (empty($optionsItems)) {
        $optionsItems = [''];
    }
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm mb-2">Название</label>
        <input name="name" type="text" required value="{{ old('name', $attribute?->name ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'name'])
    </div>
    <div>
        <label class="block text-sm mb-2">Slug (опционально)</label>
        <input name="slug" type="text" value="{{ old('slug', $attribute?->slug ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'slug'])
    </div>
    <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Категории</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Можно выбрать несколько веток. Без выбора — свойство глобальное (для всех категорий).</p>
        @include('admin.partials.category_tree_select', [
            'name' => 'product_category_ids',
            'multiple' => true,
            'categoryTree' => $categoryTree ?? collect(),
            'categories' => $categories ?? collect(),
            'selectedIds' => old('product_category_ids', $attribute
                ? ($attribute->relationLoaded('categories')
                    ? $attribute->categories->pluck('id')->all()
                    : $attribute->categories()->pluck('product_categories.id')->all())
                : []),
            'placeholder' => 'Глобальное (все категории)',
            'allowClear' => true,
            'clearLabel' => 'Глобальное (все категории)',
        ])
        @include('admin.partials.field-error', ['field' => 'product_category_ids'])
        @include('admin.partials.field-error', ['field' => 'product_category_ids.*'])
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Тип значения (карточка товара)</label>
        @include('admin.partials.styled_select', [
            'name' => 'type',
            'value' => $attribute?->type ?? 'text',
            'options' => $types,
        ])
        @include('admin.partials.field-error', ['field' => 'type'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок в карточке товара</label>
        <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $attribute?->sort_order ?? 0) }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'sort_order'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок в панели фильтров</label>
        <input name="filter_sort_order" type="number" min="0" max="65535" value="{{ old('filter_sort_order', $attribute?->filter_sort_order ?? '') }}" placeholder="Как «Порядок в карточке», если пусто" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'filter_sort_order'])
    </div>
</div>
<div class="mt-6">
    <label class="block text-sm mb-2">Опции списка (для типа «Список» и фиксированного набора фильтра)</label>
    <div data-options-editor class="space-y-2">
        <input type="hidden" name="options_raw" value="{{ $optionsRawValue }}" data-options-raw />
        <div data-options-list class="space-y-2">
            @foreach($optionsItems as $optionItem)
                <div class="flex items-center gap-2" data-option-row>
                    <input
                        type="text"
                        value="{{ $optionItem }}"
                        data-option-input
                        placeholder="Введите значение"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                    />
                    <button
                        type="button"
                        data-remove-option
                        class="px-3 py-2 text-sm text-red-600 border border-red-200 dark:border-red-500/40 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10"
                    >
                        Удалить
                    </button>
                </div>
            @endforeach
        </div>
        <button
            type="button"
            data-add-option
            class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
        >
            + Добавить значение
        </button>
    </div>
    @include('admin.partials.field-error', ['field' => 'options_raw'])
</div>

<div class="mt-6 border border-gray-200 dark:border-gray-600 rounded-xl p-4 bg-gray-50 dark:bg-gray-700/40 space-y-3 text-sm">
    <p class="font-medium text-gray-900 dark:text-white">Фильтрация каталога (ТЗ)</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Тип отображения фильтра</label>
            @include('admin.partials.styled_select', [
                'name' => 'filter_display_type',
                'value' => $attribute?->filter_display_type ?? '',
                'placeholder' => 'Автоматически по типу данных',
                'options' => $filterDisplayTypes,
            ])
            @include('admin.partials.field-error', ['field' => 'filter_display_type'])
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Допустимые значения в фильтре</label>
            @include('admin.partials.styled_select', [
                'name' => 'filter_values_source',
                'value' => $attribute?->filter_values_source ?? \App\Models\ProductAttribute::FILTER_VALUES_FIXED,
                'options' => $filterValuesSources,
            ])
            @include('admin.partials.field-error', ['field' => 'filter_values_source'])
        </div>
    </div>
    <div class="flex flex-wrap gap-4">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_filterable" value="1" @checked(old('is_filterable', $attribute?->is_filterable ?? false)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Используется в фильтрах</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="filter_allow_multiple" value="1" @checked(old('filter_allow_multiple', $attribute?->filter_allow_multiple ?? false)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Множественный выбор в фильтре</label>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $attribute?->is_required ?? false)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Обязательно к заполнению</label>
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $attribute?->is_active ?? true)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Активно</label>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-options-editor]').forEach(function (editor) {
            var list = editor.querySelector('[data-options-list]');
            var rawInput = editor.querySelector('[data-options-raw]');
            var addButton = editor.querySelector('[data-add-option]');
            var form = editor.closest('form');

            if (!list || !rawInput || !addButton || !form) {
                return;
            }

            var buildRow = function (value) {
                var row = document.createElement('div');
                row.className = 'flex items-center gap-2';
                row.setAttribute('data-option-row', '');

                row.innerHTML = '' +
                    '<input type="text" data-option-input placeholder="Введите значение" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />' +
                    '<button type="button" data-remove-option class="px-3 py-2 text-sm text-red-600 border border-red-200 dark:border-red-500/40 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10">Удалить</button>';

                row.querySelector('[data-option-input]').value = value || '';

                return row;
            };

            var syncRawInput = function () {
                var values = Array.from(list.querySelectorAll('[data-option-input]'))
                    .map(function (input) { return input.value.trim(); })
                    .filter(function (value) { return value.length > 0; });

                rawInput.value = values.join("\n");
            };

            var ensureAtLeastOneRow = function () {
                if (!list.querySelector('[data-option-row]')) {
                    list.appendChild(buildRow(''));
                }
            };

            addButton.addEventListener('click', function () {
                list.appendChild(buildRow(''));
            });

            list.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-remove-option]');
                if (!removeButton) {
                    return;
                }

                var row = removeButton.closest('[data-option-row]');
                if (row) {
                    row.remove();
                    ensureAtLeastOneRow();
                    syncRawInput();
                }
            });

            list.addEventListener('input', function (event) {
                if (event.target.matches('[data-option-input]')) {
                    syncRawInput();
                }
            });

            form.addEventListener('submit', syncRawInput);
            syncRawInput();
        });
    });
</script>

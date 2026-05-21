@php
    $filterDisplayTypes = $filterDisplayTypes ?? \App\Models\ProductAttribute::filterDisplayLabels();
    $filterValuesSources = $filterValuesSources ?? \App\Models\ProductAttribute::filterValuesSourceLabels();
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm mb-2">Название</label>
        <input name="name" type="text" required value="{{ old('name', $attribute->name ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'name'])
    </div>
    <div>
        <label class="block text-sm mb-2">Slug (опционально)</label>
        <input name="slug" type="text" value="{{ old('slug', $attribute->slug ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'slug'])
    </div>
    <div>
        <label class="block text-sm mb-2">Категория</label>
        <div class="relative">
            <select name="product_category_id" class="w-full appearance-none pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
                <option value="">Глобальное свойство (для всех)</option>
                @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('product_category_id', $attribute->product_category_id ?? '') === (string) $category->id)>
                    {{ $category->full_path }}
                </option>
                @endforeach
            </select>
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
        @include('admin.partials.field-error', ['field' => 'product_category_id'])
    </div>
    <div>
        <label class="block text-sm mb-2">Тип значения (карточка товара)</label>
        <div class="relative">
            <select name="type" class="w-full appearance-none pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
                @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $attribute->type ?? 'text') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
        @include('admin.partials.field-error', ['field' => 'type'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок в карточке товара</label>
        <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $attribute->sort_order ?? 0) }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'sort_order'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок в панели фильтров</label>
        <input name="filter_sort_order" type="number" min="0" max="65535" value="{{ old('filter_sort_order', $attribute->filter_sort_order ?? '') }}" placeholder="Как «Порядок в карточке», если пусто" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'filter_sort_order'])
    </div>
</div>
<div class="mt-6">
    <label class="block text-sm mb-2">Опции списка (каждая с новой строки; для типа «Список» и фиксированного набора фильтра)</label>
    <textarea name="options_raw" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">{{ old('options_raw', isset($attribute) && is_array($attribute->options ?? null) ? implode("\n", $attribute->options) : '') }}</textarea>
    @include('admin.partials.field-error', ['field' => 'options_raw'])
</div>

<div class="mt-6 border border-gray-200 dark:border-gray-600 rounded-xl p-4 bg-gray-50 dark:bg-gray-700/40 space-y-3 text-sm">
    <p class="font-medium text-gray-900 dark:text-white">Фильтрация каталога (ТЗ)</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm mb-2">Тип отображения фильтра</label>
            <div class="relative">
                <select name="filter_display_type" class="w-full appearance-none pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
                    <option value="">Автоматически по типу данных</option>
                    @foreach($filterDisplayTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('filter_display_type', $attribute->filter_display_type ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
            @include('admin.partials.field-error', ['field' => 'filter_display_type'])
        </div>
        <div>
            <label class="block text-sm mb-2">Допустимые значения в фильтре</label>
            <div class="relative">
                <select name="filter_values_source" class="w-full appearance-none pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">
                    @foreach($filterValuesSources as $value => $label)
                    <option value="{{ $value }}" @selected(old('filter_values_source', $attribute->filter_values_source ?? \App\Models\ProductAttribute::FILTER_VALUES_FIXED) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
            @include('admin.partials.field-error', ['field' => 'filter_values_source'])
        </div>
    </div>
    <div class="flex flex-wrap gap-4">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_filterable" value="1" @checked(old('is_filterable', $attribute->is_filterable ?? false)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Используется в фильтрах</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="filter_allow_multiple" value="1" @checked(old('filter_allow_multiple', $attribute->filter_allow_multiple ?? false)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Множественный выбор в фильтре</label>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $attribute->is_required ?? false)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Обязательно к заполнению</label>
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $attribute->is_active ?? true)) class="h-4 w-4 rounded border-gray-300 focus:ring-[#c3242a]" style="accent-color: #c3242a;" /> Активно</label>
</div>

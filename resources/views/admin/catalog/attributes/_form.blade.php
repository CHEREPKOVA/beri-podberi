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
        <select name="product_category_id" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
            <option value="">Глобальное свойство (для всех)</option>
            @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('product_category_id', $attribute->product_category_id ?? '') === (string) $category->id)>
                {{ $category->full_path }}
            </option>
            @endforeach
        </select>
        @include('admin.partials.field-error', ['field' => 'product_category_id'])
    </div>
    <div>
        <label class="block text-sm mb-2">Тип значения</label>
        <select name="type" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
            @foreach($types as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $attribute->type ?? 'text') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @include('admin.partials.field-error', ['field' => 'type'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок</label>
        <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $attribute->sort_order ?? 0) }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'sort_order'])
    </div>
</div>
<div class="mt-6">
    <label class="block text-sm mb-2">Опции списка (каждая с новой строки, только для типа "Список")</label>
    <textarea name="options_raw" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">{{ old('options_raw', isset($attribute) && is_array($attribute->options ?? null) ? implode("\n", $attribute->options) : '') }}</textarea>
    @include('admin.partials.field-error', ['field' => 'options_raw'])
</div>
<div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_filterable" value="1" @checked(old('is_filterable', $attribute->is_filterable ?? false)) /> Используется в фильтрах</label>
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $attribute->is_required ?? false)) /> Обязательно к заполнению</label>
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $attribute->is_active ?? true)) /> Активно</label>
</div>

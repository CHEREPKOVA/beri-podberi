<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm mb-2">Название</label>
        <input name="name" type="text" required value="{{ old('name', $category->name ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'name'])
    </div>
    <div>
        <label class="block text-sm mb-2">Slug (опционально)</label>
        <input name="slug" type="text" value="{{ old('slug', $category->slug ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'slug'])
    </div>
    <div>
        <label class="block text-sm mb-2">Родительская категория</label>
        <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
            <option value="">Без родителя</option>
            @foreach($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category->parent_id ?? '') === (string) $parent->id)>
                {{ $parent->full_path }}
            </option>
            @endforeach
        </select>
        @include('admin.partials.field-error', ['field' => 'parent_id'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок</label>
        <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'sort_order'])
    </div>
</div>
<div class="mt-6">
    <label class="block text-sm mb-2">Описание</label>
    <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">{{ old('description', $category->description ?? '') }}</textarea>
    @include('admin.partials.field-error', ['field' => 'description'])
</div>
<label class="mt-4 inline-flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) />
    Активна
</label>

@php $m = $method ?? null; @endphp

<div>
    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название <span class="text-red-500">*</span></label>
    <input type="text" name="name" id="name" value="{{ old('name', $m->name ?? '') }}" required
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] @error('name') border-red-500 @enderror" />
    @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код (slug) <span class="text-red-500">*</span></label>
    <input type="text" name="slug" id="slug" value="{{ old('slug', $m->slug ?? '') }}" required pattern="[a-z0-9_]+"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] font-mono text-sm @error('slug') border-red-500 @enderror" />
    <p class="mt-1 text-xs text-gray-500">Латиница, цифры и подчёркивание, например <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">self_pickup</code></p>
    @error('slug')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Описание</label>
    <textarea name="description" id="description" rows="3"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] @error('description') border-red-500 @enderror">{{ old('description', $m->description ?? '') }}</textarea>
    @error('description')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="requires_tracking" value="0" />
    <input type="checkbox" name="requires_tracking" id="requires_tracking" value="1" @checked(old('requires_tracking', $m->requires_tracking ?? false)) class="h-4 w-4 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
    <label for="requires_tracking" class="text-sm text-gray-700 dark:text-gray-300">Требуется трек-номер</label>
</div>

<div>
    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Порядок <span class="text-red-500">*</span></label>
    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $m->sort_order ?? 0) }}" min="0" required
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('sort_order') border-red-500 @enderror" />
    @error('sort_order')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0" />
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $m->is_active ?? true)) class="h-4 w-4 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Активен</label>
</div>

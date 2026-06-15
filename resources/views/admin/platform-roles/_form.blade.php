@php $item = $role ?? null; $slugLocked = $slugLocked ?? false; @endphp

<div>
    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название <span class="text-red-500">*</span></label>
    <input type="text" name="name" id="name" value="{{ old('name', $item->name ?? '') }}" required class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('name') border-red-500 @enderror" />
    @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

@if(!$slugLocked)
<div>
    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код (slug) <span class="text-red-500">*</span></label>
    <input type="text" name="slug" id="slug" value="{{ old('slug', $item->slug ?? '') }}" required pattern="[a-z0-9_]+" @readonly($item !== null)
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 font-mono text-sm @error('slug') border-red-500 @enderror {{ $item ? 'opacity-70' : '' }}" />
    @if($item)<p class="mt-1 text-xs text-gray-500">Код нельзя изменить после создания.</p>@endif
    @error('slug')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>
@else
<div>
    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код (slug)</span>
    <p class="text-sm font-mono text-gray-600 dark:text-gray-300">{{ $item->slug }}</p>
    <p class="mt-1 text-xs text-gray-500">Системная роль — код не редактируется.</p>
</div>
@endif

<div>
    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Описание</label>
    <textarea name="description" id="description" rows="3" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">{{ old('description', $item->description ?? '') }}</textarea>
    @error('description')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Порядок <span class="text-red-500">*</span></label>
    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}" min="0" required class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('sort_order') border-red-500 @enderror" />
    @error('sort_order')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0" />
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="h-4 w-4 rounded accent-[#c3242a]" />
    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Активна</label>
</div>

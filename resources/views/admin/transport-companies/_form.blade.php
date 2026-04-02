@php $c = $company ?? null; @endphp

<div>
    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название <span class="text-red-500">*</span></label>
    <input type="text" name="name" id="name" value="{{ old('name', $c->name ?? '') }}" required
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] @error('name') border-red-500 @enderror" />
    @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код (slug) <span class="text-red-500">*</span></label>
    <input type="text" name="slug" id="slug" value="{{ old('slug', $c->slug ?? '') }}" required pattern="[a-z0-9_]+"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 font-mono text-sm @error('slug') border-red-500 @enderror" />
    @error('slug')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="website" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Сайт</label>
    <input type="text" name="website" id="website" value="{{ old('website', $c->website ?? '') }}" placeholder="https://…"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('website') border-red-500 @enderror" />
    @error('website')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="tracking_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL отслеживания</label>
    <input type="text" name="tracking_url" id="tracking_url" value="{{ old('tracking_url', $c->tracking_url ?? '') }}" placeholder="Шаблон с номером отправления в конце"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('tracking_url') border-red-500 @enderror" />
    @error('tracking_url')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0" />
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $c->is_active ?? true))
        class="h-4 w-4 shrink-0 rounded border-gray-300 bg-white dark:bg-gray-700 accent-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/50 focus:ring-offset-0 dark:accent-[#c3242a]" />
    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Активна</label>
</div>
@error('is_active')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror

@php $u = $unitType ?? null; @endphp

<div>
    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Полное название <span class="text-red-500">*</span></label>
    <input type="text" name="name" id="name" value="{{ old('name', $u->name ?? '') }}" required
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('name') border-red-500 @enderror" />
    @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Краткое обозначение <span class="text-red-500">*</span></label>
    <input type="text" name="short_name" id="short_name" value="{{ old('short_name', $u->short_name ?? '') }}" required maxlength="32"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 @error('short_name') border-red-500 @enderror" />
    <p class="mt-1 text-xs text-gray-500">Например: шт., кг, уп.</p>
    @error('short_name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код <span class="text-red-500">*</span></label>
    <input type="text" name="code" id="code" value="{{ old('code', $u->code ?? '') }}" required pattern="[a-zA-Z0-9_]+" maxlength="32"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 font-mono text-sm @error('code') border-red-500 @enderror" />
    <p class="mt-1 text-xs text-gray-500">Уникальный код для системы: piece, kg, pack…</p>
    @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0" />
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $u->is_active ?? true))
        class="h-4 w-4 shrink-0 rounded border-gray-300 bg-white dark:bg-gray-700 accent-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/50 focus:ring-offset-0 dark:accent-[#c3242a]" />
    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Активна</label>
</div>
@error('is_active')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror

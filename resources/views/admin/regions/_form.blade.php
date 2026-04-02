@php
    $r = $region ?? null;
@endphp

<div>
    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Название <span class="text-red-500">*</span></label>
    <input type="text" name="name" id="name" value="{{ old('name', $r->name ?? '') }}" required
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('name') border-red-500 @enderror" />
    @error('name')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код региона</label>
    <input type="text" name="code" id="code" value="{{ old('code', $r->code ?? '') }}" maxlength="10"
        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#c3242a] focus:border-transparent @error('code') border-red-500 @enderror" />
    @error('code')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div>
    <label for="federal_district" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Федеральный округ</label>
    <div class="relative">
        <select name="federal_district" id="federal_district"
            class="w-full pl-4 pr-10 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-[#c3242a] appearance-none cursor-pointer @error('federal_district') border-red-500 @enderror">
            <option value="">— не выбран —</option>
            @foreach($districts as $d)
            <option value="{{ $d }}" {{ old('federal_district', $r->federal_district ?? '') === $d ? 'selected' : '' }}>{{ $d }}</option>
            @endforeach
        </select>
        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </div>
    @error('federal_district')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
</div>

<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0" />
    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $r->is_active ?? true))
        class="h-4 w-4 shrink-0 rounded border-gray-300 bg-white dark:bg-gray-700 accent-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/50 focus:ring-offset-0 dark:accent-[#c3242a]" />
    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Активен (доступен в списках)</label>
</div>
@error('is_active')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror

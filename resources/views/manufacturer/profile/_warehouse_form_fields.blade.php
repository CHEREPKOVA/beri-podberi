@php
    $warehouse = $warehouse ?? null;
    $formMode = $formMode ?? 'edit';
    $isAddForm = $formMode === 'add';
    $contactNames = $profile->contacts->pluck('full_name')->all();
    $responsibleValue = $isAddForm ? old('responsible_person', '') : old('responsible_person', $warehouse?->responsible_person);
    $phoneValue = $isAddForm ? old('phone', '') : old('phone', $warehouse?->phone);
    $nameValue = $isAddForm ? old('name', '') : old('name', $warehouse?->name);
    $addressValue = $isAddForm ? old('address', '') : old('address', $warehouse?->address);
    $regionValue = $isAddForm ? old('region_id', '') : old('region_id', $warehouse?->region_id);
    $typeValue = $isAddForm ? old('type', '') : old('type', $warehouse?->type);
@endphp
<div class="sm:col-span-2">
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Название <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ $nameValue }}" required autocomplete="off" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
</div>
<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Регион</label>
    <div class="relative">
        <select name="region_id" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Не указан</option>
            @foreach($regions as $region)
            <option value="{{ $region->id }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" {{ (string) $regionValue === (string) $region->id ? 'selected' : '' }}>{{ $region->name }}</option>
            @endforeach
        </select>
        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </span>
    </div>
</div>
<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Тип склада <span class="text-red-500">*</span></label>
    <div class="relative">
        <select name="type" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            @if($isAddForm)
            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" {{ $typeValue === '' || $typeValue === null ? 'selected' : '' }}>Выберите тип</option>
            @endif
            @foreach(\App\Models\Warehouse::typeLabels() as $value => $label)
            <option value="{{ $value }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" {{ $typeValue === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </span>
    </div>
</div>
<div class="sm:col-span-2">
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Адрес <span class="text-red-500">*</span></label>
    <input type="text" name="address" value="{{ $addressValue }}" required autocomplete="off" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
</div>
<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Ответственный</label>
    <div class="relative">
        <select
            name="responsible_person"
            @change="onResponsibleChange($event)"
            class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        >
            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Не указан</option>
            @if($responsibleValue && ! in_array($responsibleValue, $contactNames, true))
            <option value="{{ $responsibleValue }}" selected class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $responsibleValue }}</option>
            @endif
            @forelse($profile->contacts as $contact)
            <option
                value="{{ $contact->full_name }}"
                data-phone="{{ $contact->phone ?? '' }}"
                class="text-gray-700 dark:bg-gray-900 dark:text-gray-400"
                {{ $responsibleValue === $contact->full_name ? 'selected' : '' }}
            >
                {{ $contact->full_name }}@if($contact->position) ({{ $contact->position }})@endif
            </option>
            @empty
            <option value="" disabled class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Нет контактов — добавьте в профиле</option>
            @endforelse
        </select>
        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
            <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </span>
    </div>
</div>
<div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Контактный телефон</label>
    <input
        type="tel"
        name="phone"
        value="{{ $phoneValue }}"
        inputmode="tel"
        autocomplete="off"
        placeholder="+7 (___) ___-__-__"
        @input="formatPhone($event)"
        @blur="clearPhoneIfEmpty($event)"
        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
    >
</div>

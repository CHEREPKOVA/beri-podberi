<div x-data="{ editing: false }">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Информация о компании</h2>
        <button
            x-show="!editing"
            @click="editing = true"
            class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Редактировать
        </button>
    </div>

    <form method="POST" action="{{ route('manufacturer.profile.company.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Левая колонка --}}
            <div class="space-y-5">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Основные данные</h3>

                {{-- Полное название --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Полное название организации <span class="text-red-500">*</span>
                    </label>
                    @if($profile->isFieldLocked('full_name'))
                    <p class="text-gray-900 dark:text-white py-2">{{ $profile->full_name }}</p>
                    <p class="text-xs text-gray-500">Поле заблокировано администратором</p>
                    @else
                    <input
                        type="text"
                        name="full_name"
                        value="{{ old('full_name', $profile->full_name) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                        required
                    >
                    @endif
                    @error('full_name')<p class="text-sm text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Сокращенное название --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Сокращенное название</label>
                    <input
                        type="text"
                        name="short_name"
                        value="{{ old('short_name', $profile->short_name) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                    >
                </div>

                {{-- Юридическая форма --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Юридическая форма</label>
                    <div class="relative">
                        <select
                            name="legal_form"
                            :disabled="!editing"
                            class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                        >
                            @foreach(\App\Models\ManufacturerProfile::legalFormLabels() as $value => $label)
                            <option value="{{ $value }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" {{ $profile->legal_form === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                    </div>
                </div>

                {{-- ИНН --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        ИНН <span class="text-red-500">*</span>
                    </label>
                    @if($profile->isFieldLocked('inn'))
                    <p class="text-gray-900 dark:text-white py-2">{{ $profile->inn }}</p>
                    <p class="text-xs text-gray-500">Поле заблокировано администратором</p>
                    @else
                    <input
                        type="text"
                        name="inn"
                        value="{{ old('inn', $profile->inn) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                        maxlength="12"
                        required
                    >
                    @endif
                    @error('inn')<p class="text-sm text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- КПП --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">КПП</label>
                    <input
                        type="text"
                        name="kpp"
                        value="{{ old('kpp', $profile->kpp) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                        maxlength="9"
                    >
                </div>

                {{-- ОГРН --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">ОГРН / ОГРНИП</label>
                    <input
                        type="text"
                        name="ogrn"
                        value="{{ old('ogrn', $profile->ogrn) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                        maxlength="15"
                    >
                </div>
            </div>

            {{-- Правая колонка --}}
            <div class="space-y-5">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Адреса и реквизиты</h3>

                {{-- Юридический адрес --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Юридический адрес</label>
                    <input
                        type="text"
                        name="legal_address"
                        value="{{ old('legal_address', $profile->legal_address) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                    >
                </div>

                {{-- Фактический адрес --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Фактический адрес</label>
                    <input
                        type="text"
                        name="actual_address"
                        value="{{ old('actual_address', $profile->actual_address) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                    >
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-5 mt-5">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-5">Банковские реквизиты</h3>

                    {{-- Название банка --}}
                    <div class="mb-5">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Банк</label>
                        <input
                            type="text"
                            name="bank_name"
                            value="{{ old('bank_name', $profile->bank_name) }}"
                            :disabled="!editing"
                            class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                        >
                    </div>

                    {{-- БИК --}}
                    <div class="mb-5">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">БИК</label>
                        <input
                            type="text"
                            name="bik"
                            value="{{ old('bik', $profile->bik) }}"
                            :disabled="!editing"
                            class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                            maxlength="9"
                        >
                    </div>

                    {{-- Расчетный счет --}}
                    <div class="mb-5">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Расчётный счёт</label>
                        <input
                            type="text"
                            name="checking_account"
                            value="{{ old('checking_account', $profile->checking_account) }}"
                            :disabled="!editing"
                            class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                            maxlength="20"
                        >
                    </div>

                    {{-- Корреспондентский счет --}}
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Корреспондентский счёт</label>
                        <input
                            type="text"
                            name="correspondent_account"
                            value="{{ old('correspondent_account', $profile->correspondent_account) }}"
                            :disabled="!editing"
                            class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800"
                            maxlength="20"
                        >
                    </div>
                </div>
            </div>
        </div>

        {{-- Логотип и описание --}}
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Логотип --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Логотип компании</label>
                    <div class="flex items-start gap-4 mt-2">
                        @if($profile->logo)
                        <img src="{{ $profile->logo_url }}" alt="Логотип" class="w-24 h-24 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                        @else
                        <div class="w-24 h-24 flex items-center justify-center rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        @endif
                        <div x-show="editing">
                            <input
                                type="file"
                                name="logo"
                                accept="image/jpeg,image/png"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#c3242a] file:text-white hover:file:bg-[#a01e24] file:cursor-pointer"
                            >
                            <p class="text-xs text-gray-500 mt-2">JPG или PNG, до 5 МБ</p>
                        </div>
                    </div>
                    @error('logo')<p class="text-sm text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Описание --}}
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Краткое описание компании</label>
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="1000"
                        :disabled="!editing"
                        x-ref="desc"
                        class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 disabled:bg-gray-100 dark:disabled:bg-gray-800 resize-none"
                        placeholder="Расскажите о вашей компании (до 1000 символов)"
                    >{{ old('description', $profile->description) }}</textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        <span x-text="($refs.desc?.value?.length || {{ strlen($profile->description ?? '') }})"></span> / 1000 символов
                    </p>
                </div>
            </div>
        </div>

        {{-- Кнопки --}}
        <div x-show="editing" class="mt-6 flex items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Сохранить изменения
            </button>
            <button
                type="button"
                @click="editing = false; $el.form.reset()"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-theme-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                Отменить
            </button>
        </div>
    </form>
</div>

<div x-data="{ editing: false }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Общая информация о компании</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Базовые сведения, логотип и ответственный сотрудник</p>
        </div>
        <button
            x-show="!editing"
            type="button"
            @click="editing = true"
            class="inline-flex items-center gap-2 px-4 py-2 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition"
        >
            Редактировать
        </button>
    </div>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 text-sm">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <dt class="text-gray-500 dark:text-gray-400">Дата регистрации в системе</dt>
            <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ $profile->created_at->format('d.m.Y') }}</dd>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <dt class="text-gray-500 dark:text-gray-400">Ответственный (основной контакт)</dt>
            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                @if($profile->primaryContact())
                    {{ $profile->primaryContact()->full_name }}
                    <span class="block text-xs font-normal text-gray-500 mt-0.5">{{ $profile->primaryContact()->email }}</span>
                @else
                    <span class="text-gray-400">Добавьте контакт во вкладке «Контакты»</span>
                @endif
            </dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('end_company.profile.general.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @if($profile->isFieldLocked('full_name'))
            <input type="hidden" name="full_name" value="{{ $profile->full_name }}">
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Полное наименование <span class="text-red-500">*</span></label>
                    @if($profile->isFieldLocked('full_name'))
                        <p class="text-gray-900 dark:text-white py-2">{{ $profile->full_name }}</p>
                        <p class="text-xs text-gray-500">Изменение только через поддержку / администратора.</p>
                    @else
                        <input type="text" name="full_name" value="{{ old('full_name', $profile->full_name) }}" required
                            :disabled="!editing"
                            class="shadow-theme-xs focus:border-[#c3242a] h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    @endif
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Краткое наименование</label>
                    <input type="text" name="short_name" value="{{ old('short_name', $profile->short_name) }}"
                        :disabled="!editing"
                        class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Форма собственности</label>
                    <select name="legal_form" :disabled="!editing" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                        @foreach(\App\Models\EndCompanyProfile::legalFormLabels() as $value => $label)
                            <option value="{{ $value }}" {{ $profile->legal_form === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">ИНН</label>
                    <p class="text-gray-900 dark:text-white py-2">{{ $profile->inn ?: '—' }}</p>
                    <p class="text-xs text-gray-500">Редактируется во вкладке «Юридические реквизиты» или администратором.</p>
                </div>
            </div>
            <div class="space-y-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Логотип</label>
                    <div class="flex items-start gap-4 mt-2">
                        @if($profile->logo)
                            <img src="{{ $profile->logo_url }}" alt="" class="w-24 h-24 object-contain rounded-lg border border-gray-200 dark:border-gray-700">
                        @else
                            <div class="w-24 h-24 flex items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-gray-400 text-xs text-center p-2">Нет файла</div>
                        @endif
                        <div x-show="editing">
                            <input type="file" name="logo" accept="image/jpeg,image/png" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#c3242a] file:text-white">
                            <p class="text-xs text-gray-500 mt-1">JPG или PNG, до 5 МБ</p>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Сопроводительное описание</label>
                    <textarea name="description" rows="4" maxlength="1000" :disabled="!editing"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">{{ old('description', $profile->description) }}</textarea>
                </div>
            </div>
        </div>

        <div x-show="editing" class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24]">Сохранить</button>
            <button type="button" @click="editing = false" class="px-5 py-2.5 border border-gray-300 text-sm rounded-lg dark:border-gray-600">Отменить</button>
        </div>
    </form>
</div>

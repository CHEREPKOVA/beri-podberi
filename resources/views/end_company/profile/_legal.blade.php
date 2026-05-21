<div x-data="{ editing: false }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Юридические реквизиты</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Используются дистрибьюторами при выставлении документов</p>
        </div>
        <button type="button" x-show="!editing" @click="editing = true" class="px-4 py-2 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24]">Редактировать</button>
    </div>

    <form method="POST" action="{{ route('end_company.profile.legal.update') }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">ИНН</label>
                    @if($profile->isFieldLocked('inn'))
                        <p class="py-2 text-gray-900 dark:text-white">{{ $profile->inn ?: '—' }}</p>
                        <p class="text-xs text-gray-500">Поле заблокировано администратором.</p>
                    @else
                        <input type="text" name="inn" value="{{ old('inn', $profile->inn) }}" maxlength="12" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    @endif
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">КПП</label>
                    @if($profile->isFieldLocked('kpp'))
                        <p class="py-2">{{ $profile->kpp ?: '—' }}</p>
                    @else
                        <input type="text" name="kpp" value="{{ old('kpp', $profile->kpp) }}" maxlength="9" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    @endif
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">ОГРН / ОГРНИП</label>
                    @if($profile->isFieldLocked('ogrn'))
                        <p class="py-2">{{ $profile->ogrn ?: '—' }}</p>
                    @else
                        <input type="text" name="ogrn" value="{{ old('ogrn', $profile->ogrn) }}" maxlength="15" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    @endif
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Руководитель / ИП (ФИО)</label>
                    <input type="text" name="director_name" value="{{ old('director_name', $profile->director_name) }}" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Юридический адрес</label>
                    <input type="text" name="legal_address" value="{{ old('legal_address', $profile->legal_address) }}" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Фактический адрес</label>
                    <input type="text" name="actual_address" value="{{ old('actual_address', $profile->actual_address) }}" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Банковские реквизиты</p>
                    <input type="text" name="bank_name" placeholder="Банк" value="{{ old('bank_name', $profile->bank_name) }}" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    <input type="text" name="bik" placeholder="БИК" value="{{ old('bik', $profile->bik) }}" maxlength="9" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    <input type="text" name="checking_account" placeholder="Расчётный счёт" value="{{ old('checking_account', $profile->checking_account) }}" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    <input type="text" name="correspondent_account" placeholder="Корр. счёт" value="{{ old('correspondent_account', $profile->correspondent_account) }}" :disabled="!editing" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 dark:disabled:bg-gray-800">
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-4">Данные в уже оформленных заказах не меняются задним числом — актуальны для новых заказов.</p>

        <div x-show="editing" class="mt-6 flex gap-3">
            <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24]">Сохранить</button>
            <button type="button" @click="editing = false" class="px-5 py-2.5 border border-gray-300 text-sm rounded-lg dark:border-gray-600">Отменить</button>
        </div>
    </form>
</div>

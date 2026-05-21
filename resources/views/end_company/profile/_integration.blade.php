<div>
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Интеграции</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Настройки обмена с внешними системами (по мере подключения модулей).</p>

    <form method="POST" action="{{ route('end_company.profile.integration.update') }}" class="max-w-xl space-y-5">
        @csrf
        @method('PUT')
        <label class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer has-[:checked]:border-[#c3242a]">
            <input type="checkbox" name="integration_edi_enabled" value="1" class="mt-1 h-5 w-5 accent-[#c3242a]" {{ old('integration_edi_enabled', $profile->integration_edi_enabled) ? 'checked' : '' }}>
            <span class="text-sm"><span class="font-medium text-gray-900 dark:text-white">Электронный документооборот / EDI</span>
                <span class="block text-gray-500 mt-0.5">Учётная политика обмена уточняется с поддержкой</span>
            </span>
        </label>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">URL вебхука (если используется)</label>
            <input type="url" name="integration_webhook_url" value="{{ old('integration_webhook_url', $profile->integration_webhook_url) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="https://...">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Комментарий</label>
            <textarea name="integration_comment" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">{{ old('integration_comment', $profile->integration_comment) }}</textarea>
        </div>
        <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24]">Сохранить</button>
    </form>
</div>

<div>
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Настройки интеграции</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Импорт остатков и выгрузка заказов (CSV, YML, 1С). Детальная настройка обмена может дополняться по мере внедрения модулей.</p>
    </div>

    <form method="POST" action="{{ route('distributor.profile.integration.update') }}">
        @csrf
        @method('PUT')

        <div class="space-y-6 max-w-3xl">
            <label class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                <input type="checkbox" name="integration_csv_enabled" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 accent-[#c3242a]" {{ old('integration_csv_enabled', $profile->integration_csv_enabled) ? 'checked' : '' }}>
                <div>
                    <div class="font-medium text-gray-900 dark:text-white">Обмен по CSV</div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Использовать выгрузки/загрузки в формате CSV</p>
                </div>
            </label>

            <label class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                <input type="checkbox" name="integration_yml_enabled" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 accent-[#c3242a]" {{ old('integration_yml_enabled', $profile->integration_yml_enabled) ? 'checked' : '' }}>
                <div>
                    <div class="font-medium text-gray-900 dark:text-white">Каталог / прайс YML</div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Подключение YML-фида для каталога или маркетплейсов</p>
                </div>
            </label>

            <label class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                <input type="checkbox" name="integration_import_1c_stocks" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 accent-[#c3242a]" {{ old('integration_import_1c_stocks', $profile->integration_import_1c_stocks) ? 'checked' : '' }}>
                <div>
                    <div class="font-medium text-gray-900 dark:text-white">Импорт остатков из 1С</div>
                </div>
            </label>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                <div class="font-medium text-gray-900 dark:text-white">Поведение при нулевых остатках</div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Определяет, что видит конечная компания, если на всех ваших складах по товару остаток равен нулю.
                </p>
                @php
                    $zeroBehavior = old('zero_stock_behavior', $profile->zeroStockBehavior());
                @endphp
                <div class="space-y-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="radio"
                            name="zero_stock_behavior"
                            value="{{ \App\Models\DistributorProfile::ZERO_STOCK_ON_ORDER }}"
                            class="mt-1 h-4 w-4 rounded border-gray-300 accent-[#c3242a]"
                            {{ $zeroBehavior === \App\Models\DistributorProfile::ZERO_STOCK_ON_ORDER ? 'checked' : '' }}
                        >
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">Показывать как «Под заказ»</div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Товары остаются в каталоге, но в блоке остатков отображаются как «Под заказ». Клиент понимает, что требуется ожидание поставки.
                            </p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="radio"
                            name="zero_stock_behavior"
                            value="{{ \App\Models\DistributorProfile::ZERO_STOCK_HIDE }}"
                            class="mt-1 h-4 w-4 rounded border-gray-300 accent-[#c3242a]"
                            {{ $zeroBehavior === \App\Models\DistributorProfile::ZERO_STOCK_HIDE ? 'checked' : '' }}
                        >
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">Скрывать из доступных товаров</div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                При нулевом остатке товар не будет считаться доступным к заказу. Отображение зависит от общих настроек каталога (может быть скрыт полностью).
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <label class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                <input type="checkbox" name="integration_export_orders_1c" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 accent-[#c3242a]" {{ old('integration_export_orders_1c', $profile->integration_export_orders_1c) ? 'checked' : '' }}>
                <div>
                    <div class="font-medium text-gray-900 dark:text-white">Выгрузка заказов в 1С</div>
                </div>
            </label>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">URL или путь к CSV-фиду (если применимо)</label>
                <input type="url" name="integration_csv_feed_url" value="{{ old('integration_csv_feed_url', $profile->integration_csv_feed_url) }}" placeholder="https://..." class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">URL YML-каталога (если применимо)</label>
                <input type="url" name="integration_yml_feed_url" value="{{ old('integration_yml_feed_url', $profile->integration_yml_feed_url) }}" placeholder="https://..." class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Комментарий для службы поддержки / интегратора</label>
                <textarea name="integration_comment" rows="4" maxlength="2000" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 resize-none" placeholder="Расписание обмена, учётные системы, контакт ответственного за 1С…">{{ old('integration_comment', $profile->integration_comment) }}</textarea>
            </div>
        </div>

        <div class="mt-8">
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Сохранить
            </button>
        </div>
    </form>
</div>

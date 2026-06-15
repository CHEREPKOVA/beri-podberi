<div>
    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Отметки в каталоге</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Отображаются на карточке товара для покупателей.</p>
    <div class="flex flex-wrap gap-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="mark_is_new" value="1" @checked(old('mark_is_new', $product?->mark_is_new))
                   class="h-4 w-4 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
            Новинка
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="mark_on_sale" value="1" @checked(old('mark_on_sale', $product?->mark_on_sale))
                   class="h-4 w-4 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
            Распродажа
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="mark_discontinued" value="1" @checked(old('mark_discontinued', $product?->mark_discontinued))
                   class="h-4 w-4 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
            Снят с производства
        </label>
    </div>
</div>

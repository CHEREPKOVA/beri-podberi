<div class="space-y-8">
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Статус публикации</h3>

        @if($product && !$product->canBePublished())
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg mb-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div>
                    <p class="font-medium">Невозможно опубликовать товар</p>
                    <p class="text-sm mt-1">Заполните обязательные поля для публикации:</p>
                    <ul class="text-sm mt-2 list-disc list-inside">
                        @if(empty($product->name))<li>Наименование товара</li>@endif
                        @if(empty($product->base_price))<li>Базовая цена</li>@endif
                        @if(empty($product->category_id))<li>Категория</li>@endif
                        @if(!$product->hasStock())<li>Остатки на складах</li>@endif
                    </ul>
                </div>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4" x-data="{ status: '{{ old('status', $product?->status ?? 'draft') }}' }">
            <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors"
                :class="status === 'active' ? 'border-green-500 bg-green-50 ring-2 ring-green-500/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="status" value="active" class="sr-only" x-model="status" />
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Активен</p>
                        <p class="text-xs text-gray-500">Виден в каталоге</p>
                    </div>
                </div>
            </label>

            <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors"
                :class="status === 'hidden' ? 'border-gray-500 bg-gray-50 ring-2 ring-gray-500/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="status" value="hidden" class="sr-only" x-model="status" />
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Скрыт</p>
                        <p class="text-xs text-gray-500">Не виден в каталоге</p>
                    </div>
                </div>
            </label>

            <label class="relative flex cursor-pointer rounded-lg border p-4 transition-colors"
                :class="status === 'draft' ? 'border-yellow-500 bg-yellow-50 ring-2 ring-yellow-500/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="status" value="draft" class="sr-only" x-model="status" />
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Черновик</p>
                        <p class="text-xs text-gray-500">В процессе заполнения</p>
                    </div>
                </div>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Дата публикации
            </label>
            <input type="datetime-local" name="published_at"
                value="{{ old('published_at', $product?->published_at?->format('Y-m-d\TH:i')) }}"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            <p class="mt-1 text-xs text-gray-500">Оставьте пустым для немедленной публикации</p>
        </div>

        <div x-data="{ checked: {{ old('show_in_catalog', $product?->show_in_catalog) ? 'true' : 'false' }} }" class="flex items-center">
            <label class="flex cursor-pointer items-center select-none">
                <div class="relative">
                    <input type="checkbox" name="show_in_catalog" value="1" class="sr-only" x-model="checked">
                    <div @click="checked = !checked"
                        :class="checked ? 'border-[#c3242a] bg-[#c3242a]' : 'bg-transparent border-gray-300 dark:border-gray-600'"
                        class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-colors hover:border-[#c3242a] dark:hover:border-[#c3242a] cursor-pointer">
                        <span :class="checked ? 'opacity-100' : 'opacity-0'" class="transition-opacity">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                    </div>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Показывать в общем каталоге</span>
                    <p class="text-xs text-gray-500">Товар будет виден всем покупателям</p>
                </div>
            </label>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Региональная доступность</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Выберите регионы, в которых товар будет доступен для заказа. Если не выбран ни один регион — товар доступен везде.
        </p>

        <div x-data="{ 
            selectAll: {{ $product?->availableRegions->count() === 0 || $product?->availableRegions->count() === $regions->count() ? 'true' : 'false' }},
            regions: {}
        }" x-init="regions = { @foreach($regions as $region){{ $region->id }}: {{ in_array($region->id, old('available_regions', $product?->availableRegions->pluck('id')->toArray() ?? [])) ? 'true' : 'false' }},@endforeach }">
            <label class="flex cursor-pointer items-center mb-4 select-none">
                <div class="relative">
                    <div @click="selectAll = !selectAll; if(selectAll) { Object.keys(regions).forEach(k => regions[k] = false); }"
                        :class="selectAll ? 'border-[#c3242a] bg-[#c3242a]' : 'bg-transparent border-gray-300 dark:border-gray-600'"
                        class="mr-2.5 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-colors hover:border-[#c3242a] dark:hover:border-[#c3242a] cursor-pointer">
                        <span :class="selectAll ? 'opacity-100' : 'opacity-0'" class="transition-opacity">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                    </div>
                </div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Все регионы</span>
            </label>

            <div x-show="!selectAll" x-cloak class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg max-h-64 overflow-y-auto">
                @foreach($regions as $region)
                <label class="flex cursor-pointer items-center select-none">
                    <div class="relative">
                        <input type="checkbox" name="available_regions[]" value="{{ $region->id }}" class="sr-only" x-model="regions[{{ $region->id }}]" @change="selectAll = false">
                        <div @click="regions[{{ $region->id }}] = !regions[{{ $region->id }}]; selectAll = false"
                            :class="regions[{{ $region->id }}] ? 'border-[#c3242a] bg-[#c3242a]' : 'bg-transparent border-gray-300 dark:border-gray-600'"
                            class="mr-2 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-colors hover:border-[#c3242a] dark:hover:border-[#c3242a] cursor-pointer">
                            <span :class="regions[{{ $region->id }}] ? 'opacity-100' : 'opacity-0'" class="transition-opacity">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $region->name }}</span>
                </label>
                @endforeach
            </div>
        </div>
    </div>

    @if($product)
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Информация о публикации</h4>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Статус</dt>
                <dd class="font-medium">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $product->statusBadgeClass() }}">
                        {{ $product->statusLabel() }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Дата публикации</dt>
                <dd class="font-medium">{{ $product->published_at?->format('d.m.Y H:i') ?? 'Не опубликован' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Создан</dt>
                <dd class="font-medium">{{ $product->created_at->format('d.m.Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Последнее обновление</dt>
                <dd class="font-medium">{{ $product->updated_at->format('d.m.Y H:i') }}</dd>
            </div>
            @if($product->isSynced())
            <div>
                <dt class="text-gray-500">Источник синхронизации</dt>
                <dd class="font-medium">{{ strtoupper($product->sync_source) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Последняя синхронизация</dt>
                <dd class="font-medium">{{ $product->synced_at?->format('d.m.Y H:i') }}</dd>
            </div>
            @endif
        </dl>
    </div>
    @endif
</div>

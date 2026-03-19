<div class="space-y-8">
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Артикулы и коды</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Артикул производителя
                </label>
                <input type="text" name="manufacturer_sku" value="{{ old('manufacturer_sku', $product?->manufacturer_sku) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Артикул дистрибьютора
                </label>
                <input type="text" name="distributor_sku" value="{{ old('distributor_sku', $product?->distributor_sku) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">EAN</label>
                <input type="text" name="ean" value="{{ old('ean', $product?->ean) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Штрихкод</label>
                <input type="text" name="barcode" value="{{ old('barcode', $product?->barcode) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Срок годности и условия хранения</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Срок годности
                </label>
                <input type="date" name="expiry_date" value="{{ old('expiry_date', $product?->expiry_date?->format('Y-m-d')) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Условия хранения
                </label>
                <input type="text" name="storage_conditions" value="{{ old('storage_conditions', $product?->storage_conditions) }}"
                    placeholder="Например: при температуре от +5 до +25°C"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Условия транспортировки
                </label>
                <input type="text" name="transport_conditions" value="{{ old('transport_conditions', $product?->transport_conditions) }}"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Инструкция</h3>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Ссылка на инструкцию (PDF или URL)
            </label>
            <input type="url" name="instruction_url" value="{{ old('instruction_url', $product?->instruction_url) }}"
                placeholder="https://..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        </div>
    </div>

    <div x-data="{ showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Документы</h3>
        </div>

        @if($product && $product->documents->count() > 0)
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg divide-y divide-gray-200 dark:divide-gray-600 mb-4">
            @foreach($product->documents as $document)
            <div class="flex items-center justify-between p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white dark:bg-gray-800 rounded-lg">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $document->name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $document->typeLabel() }} · {{ $document->file_size_for_humans }}
                            @if($document->valid_until)
                            · Действует до {{ $document->valid_until->format('d.m.Y') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $document->url }}" target="_blank" class="p-2 text-gray-400 hover:text-[#c3242a] rounded-lg hover:bg-gray-100" title="Открыть">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                    <button type="button"
                        @click="deleteFormAction = '{{ route('manufacturer.products.document.delete', $document) }}'; deleteMessage = {{ json_encode('Удалить документ «' . $document->name . '»?') }}; showDeleteModal = true"
                        class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100"
                        title="Удалить">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Модальное окно подтверждения удаления документа --}}
        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление документа</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
                <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
                </form>
            </div>
        </div>
        @endif

        <div x-data="{ documents: [] }">
            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
                <input type="file" name="documents[]" id="product-documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="hidden"
                    @change="documents = Array.from($event.target.files)" />
                <label for="product-documents" class="cursor-pointer">
                    <svg class="mx-auto w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Нажмите для загрузки документов</p>
                    <p class="text-xs text-gray-500">PDF, DOC, JPG, PNG до 10 МБ</p>
                </label>
            </div>

            <template x-if="documents.length > 0">
                <div class="mt-4 space-y-2">
                    <template x-for="(file, index) in documents" :key="index">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="flex-1 text-sm text-gray-700 dark:text-gray-300" x-text="file.name"></span>
                            <div class="relative">
                                <select :name="'document_types['+index+']'" class="appearance-none pl-2 pr-7 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 cursor-pointer">
                                    @foreach(\App\Models\ProductDocument::typeLabels() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <svg class="absolute right-1.5 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

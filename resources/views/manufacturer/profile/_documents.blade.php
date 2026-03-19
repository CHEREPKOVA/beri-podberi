<div x-data="{ showUploadForm: false, showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Документы компании</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Официальные документы, доступные дистрибьюторам и администраторам</p>
        </div>
        <button
            @click="showUploadForm = true"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Загрузить документ
        </button>
    </div>

    {{-- Список документов --}}
    <div class="space-y-3">
        @forelse($profile->documents as $document)
        <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-700">
                    @if(str_contains($document->mime_type ?? '', 'pdf'))
                    <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 15.5v1h1v-1h-1zm0-1h1v-1h-1v1zm0-2h1v-1h-1v1zm2.5 3v1h1v-1h-1zm0-1h1v-1h-1v1zm0-2h1v-1h-1v1zm2.5 3v1h1v-1h-1zm0-1h1v-1h-1v1zm0-2h1v-1h-1v1z"/>
                    </svg>
                    @elseif(str_contains($document->mime_type ?? '', 'image'))
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    @else
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    @endif
                </div>
                <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ $document->name }}</div>
                    <div class="flex items-center gap-3 mt-1 text-sm text-gray-500 dark:text-gray-400 flex-wrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                            {{ $document->typeLabel() }}
                        </span>
                        <span>{{ $document->file_size_formatted }}</span>
                        @if($document->valid_until)
                            @if($document->isExpired())
                            <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Истёк {{ $document->valid_until->format('d.m.Y') }}
                            </span>
                            @elseif($document->isExpiringSoon())
                            <span class="inline-flex items-center gap-1 text-yellow-600 dark:text-yellow-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                Истекает {{ $document->valid_until->format('d.m.Y') }}
                            </span>
                            @else
                            <span class="text-gray-500">До {{ $document->valid_until->format('d.m.Y') }}</span>
                            @endif
                        @endif
                    </div>
                    @if($document->notes)
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $document->notes }}</div>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ $document->url }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Просмотр
                </a>
                <button type="button"
                        @click="deleteFormAction = '{{ route('manufacturer.profile.documents.delete', $document) }}'; deleteMessage = {{ json_encode('Удалить документ «' . $document->name . '»?') }}; showDeleteModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Удалить
                    </button>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Документы не загружены</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Загрузите официальные документы вашей компании</p>
        </div>
        @endforelse
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

    {{-- Модальное окно загрузки документа --}}
    <div x-show="showUploadForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showUploadForm = false">
        <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-xl" @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Загрузить документ</h3>
            </div>
            <form method="POST" action="{{ route('manufacturer.profile.documents.store') }}" enctype="multipart/form-data" class="p-6 space-y-5">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Название документа <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Тип документа <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select name="type" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            @foreach(\App\Models\ManufacturerDocument::typeLabels() as $value => $label)
                            <option value="{{ $value }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $label }}</option>
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
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Файл <span class="text-red-500">*</span></label>
                    <input
                        type="file"
                        name="file"
                        required
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#c3242a] file:text-white hover:file:bg-[#a01e24] file:cursor-pointer"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">PDF, JPG, PNG, DOC, DOCX. Максимум 10 МБ.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Срок действия</label>
                    <input type="date" name="valid_until" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Укажите, если документ имеет срок действия (лицензии, сертификаты)</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Примечание</label>
                    <textarea name="notes" rows="2" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 resize-none"></textarea>
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs">
                        Загрузить
                    </button>
                    <button type="button" @click="showUploadForm = false" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-theme-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                        Отменить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

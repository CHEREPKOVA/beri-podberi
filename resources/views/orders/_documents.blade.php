{{-- Документы по заказу --}}
@php
    $canMutate = $can_mutate_documents ?? false;
    $types = $document_types ?? [];
    $storeRoute = $document_store_route ?? null;
    $downloadRouteName = $document_download_route ?? null;
    $previewRouteName = $document_preview_route ?? null;
    $destroyRouteName = $document_destroy_route ?? null;
    $uploaderRole = $uploader_role ?? null;
    $btnPrimary = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-[#c3242a] text-white hover:bg-[#a01e24] hover:border-[#a01e24]';
    $btnOutline = 'inline-flex items-center justify-center h-9 px-3 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-transparent text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/20';
    $btnGhost = 'inline-flex items-center justify-center h-9 px-3 rounded-lg text-sm border-2 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
    $inputBrand = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25';
@endphp
<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4" x-data="{ uploadOpen: false }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Документы по заказу</h2>
        @if($canMutate && $storeRoute)
            <button type="button" @click="uploadOpen = !uploadOpen" class="{{ $btnOutline }}">
                Загрузить документ
            </button>
        @endif
    </div>

    @if($canMutate && $storeRoute)
        <div x-show="uploadOpen" x-cloak class="rounded-lg border-2 border-[#c3242a]/30 bg-red-50/40 dark:bg-red-900/10 p-4">
            <form method="POST" action="{{ $storeRoute }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Название *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="{{ $inputBrand }}">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Тип *</label>
                    <div class="relative">
                        <select name="type" required
                                class="{{ $inputBrand }} appearance-none pr-10 cursor-pointer">
                            <option value="">— выберите —</option>
                            @foreach($types as $slug => $label)
                                <option value="{{ $slug }}" @selected(old('type') === $slug)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Файл *</label>
                    <input type="file"
                           name="file"
                           required
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                           class="block w-full text-sm text-gray-600 dark:text-gray-300
                                  file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-medium file:bg-[#c3242a] file:text-white
                                  hover:file:bg-[#a01e24] file:cursor-pointer cursor-pointer">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Комментарий</label>
                    <textarea name="notes" rows="2" class="{{ $inputBrand }}">{{ old('notes') }}</textarea>
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="{{ $btnPrimary }}">Сохранить</button>
                    <button type="button" @click="uploadOpen = false" class="{{ $btnGhost }}">Отмена</button>
                </div>
            </form>
        </div>
    @elseif(! $canMutate)
        <p class="text-sm text-gray-500">Загрузка и изменение документов недоступны для текущего статуса заказа.</p>
    @endif

    @if($order->documents->isEmpty())
        <p class="text-sm text-gray-500">Документов пока нет.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left py-2 pr-3">Название</th>
                        <th class="text-left py-2 pr-3">Тип</th>
                        <th class="text-left py-2 pr-3">Файл</th>
                        <th class="text-left py-2 pr-3">Дата</th>
                        <th class="text-left py-2 pr-3">Кто загрузил</th>
                        <th class="text-left py-2">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->documents as $document)
                        <tr class="border-b border-gray-100 dark:border-gray-700/60 align-top">
                            <td class="py-3 pr-3">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $document->name }}</p>
                                @if($document->notes)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $document->notes }}</p>
                                @endif
                            </td>
                            <td class="py-3 pr-3">
                                <p>{{ $document->typeLabel() }}</p>
                                <p class="text-xs text-gray-500">{{ $document->uploaderRoleLabel() }}</p>
                            </td>
                            <td class="py-3 pr-3 whitespace-nowrap">
                                {{ $document->fileExtension() }}
                                <span class="text-xs text-gray-500">· {{ $document->file_size_formatted }}</span>
                            </td>
                            <td class="py-3 pr-3 whitespace-nowrap">{{ $document->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="py-3 pr-3">{{ $document->uploadedBy?->name ?? '—' }}</td>
                            <td class="py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if($previewRouteName && $document->isPreviewable())
                                        <a href="{{ route($previewRouteName, [$order, $document]) }}" target="_blank" rel="noopener" class="{{ $btnOutline }}">Просмотреть</a>
                                    @endif
                                    @if($downloadRouteName)
                                        <a href="{{ route($downloadRouteName, [$order, $document]) }}" class="{{ $btnOutline }}">Скачать</a>
                                    @endif
                                    @if(
                                        $canMutate
                                        && $destroyRouteName
                                        && $uploaderRole
                                        && $document->uploader_role === $uploaderRole
                                    )
                                        <form method="POST" action="{{ route($destroyRouteName, [$order, $document]) }}"
                                              onsubmit="return confirm('Удалить документ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="{{ $btnGhost }}">Удалить</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

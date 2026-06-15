@php
    $documents = $product->relationLoaded('documents') ? $product->documents : $product->documents()->get();
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Техническая документация</h2>
    @if($documents->isNotEmpty())
        <div class="space-y-2">
            @foreach($documents as $document)
                @php
                    $isPreviewable = str_starts_with((string) $document->mime_type, 'image/')
                        || in_array($document->mime_type, ['application/pdf'], true);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $document->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $document->typeLabel() }} · {{ $document->file_size_for_humans }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($isPreviewable)
                            <a href="{{ $document->url }}" target="_blank" rel="noopener"
                               class="text-sm text-gray-600 dark:text-gray-300 hover:text-[#c3242a]">Просмотр</a>
                        @endif
                        <a href="{{ $document->url }}" download
                           class="text-sm text-[#c3242a] hover:text-[#a01e24]">Скачать</a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Документы не прикреплены.</p>
    @endif
</section>

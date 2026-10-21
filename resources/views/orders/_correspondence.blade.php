{{-- Внутренняя переписка по заказу --}}
@php
    use App\Models\PlatformOrderMessage;
    $messages = $order_messages ?? collect();
    $unread = (int) ($correspondence_unread ?? 0);
    $storeRoute = $correspondence_store_route ?? null;
    $downloadRouteName = $correspondence_download_route ?? null;
    $previewRouteName = $correspondence_preview_route ?? null;
    $destroyRouteName = $correspondence_destroy_route ?? null;
    $canPost = $correspondence_can_post ?? false;
    $canModerate = $correspondence_can_moderate ?? false;
    $currentUserId = auth()->id();
    $btnPrimary = 'inline-flex items-center justify-center h-10 px-4 rounded-lg text-sm font-medium border-2 border-[#c3242a] bg-[#c3242a] text-white hover:bg-[#a01e24] hover:border-[#a01e24]';
    $inputBrand = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25';
@endphp
<section id="order-correspondence" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Переписка по заказу</h2>
            @if($unread > 0)
                <span class="inline-flex items-center rounded-full bg-[#c3242a] px-2 py-0.5 text-[11px] font-medium text-white">
                    {{ $unread }} {{ $unread === 1 ? 'новое' : 'новых' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500">Только участники этого заказа</p>
    </div>

    <div class="max-h-[32rem] overflow-y-auto space-y-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40 p-3">
        @forelse($messages as $message)
            @php
                $isSystem = $message->isSystem();
                $isMine = ! $isSystem && $currentUserId && (int) $message->author_user_id === (int) $currentUserId;
                $isDeleted = $message->trashed();
            @endphp
            @if($isSystem)
                <div class="flex justify-center">
                    <div class="max-w-xl rounded-lg bg-gray-200/80 dark:bg-gray-700/70 px-3 py-2 text-center {{ $isDeleted ? 'opacity-60' : '' }}">
                        <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Система · {{ $message->created_at?->format('d.m.Y H:i') }}</p>
                        <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-200">{{ $message->body }}</p>
                    </div>
                </div>
            @else
                <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-xl w-full sm:w-auto sm:min-w-[18rem] rounded-xl border px-3 py-2 {{ $isMine ? 'border-[#c3242a]/30 bg-red-50/70 dark:bg-red-900/20' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800' }} {{ $isDeleted ? 'opacity-60' : '' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $message->authorName() }}</p>
                                <p class="text-[11px] text-gray-500">{{ $message->roleLabel() }} · {{ $message->created_at?->format('d.m.Y H:i') }}</p>
                            </div>
                            @if($canModerate && $destroyRouteName && ! $isDeleted)
                                <form method="POST" action="{{ route($destroyRouteName, [$order, $message]) }}" onsubmit="return confirm('Удалить сообщение из переписки?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] text-[#c3242a] hover:underline">Удалить</button>
                                </form>
                            @endif
                        </div>
                        @if($isDeleted)
                            <p class="mt-2 text-[11px] uppercase tracking-wide text-amber-700 dark:text-amber-300">Удалено администратором</p>
                        @endif
                        <p class="mt-2 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-line">{{ $message->body }}</p>
                        @if($message->attachments->isNotEmpty())
                            <ul class="mt-2 space-y-1">
                                @foreach($message->attachments as $attachment)
                                    <li class="flex flex-wrap items-center gap-2 text-xs">
                                        <span class="text-gray-600 dark:text-gray-300">{{ $attachment->original_name }}</span>
                                        @if($downloadRouteName)
                                            <a href="{{ route($downloadRouteName, [$order, $attachment]) }}" class="text-[#c3242a] hover:underline">Скачать</a>
                                        @endif
                                        @if($previewRouteName && $attachment->isPreviewable())
                                            <a href="{{ route($previewRouteName, [$order, $attachment]) }}" target="_blank" rel="noopener" class="text-gray-500 hover:underline">Просмотр</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <p class="text-sm text-gray-500 text-center py-6">Сообщений пока нет. Начните переписку по этому заказу.</p>
        @endforelse
    </div>

    @if($canPost && $storeRoute)
        <form method="POST" action="{{ $storeRoute }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Сообщение</label>
                <textarea name="body" rows="3" maxlength="5000" placeholder="Напишите сообщение участникам заказа…" class="{{ $inputBrand }}">{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1 text-xs text-[#c3242a]">{{ $message }}</p>
                @enderror
            </div>
            <div x-data="{
                fileLabel: 'файлы не выбраны',
                fileError: '',
                maxBytes: 10 * 1024 * 1024,
                maxFiles: 10,
                onFilesSelected(event) {
                    const input = event.target;
                    const files = Array.from(input.files || []);
                    this.fileError = '';
                    if (files.length === 0) {
                        this.fileLabel = 'файлы не выбраны';
                        return;
                    }
                    if (files.length > this.maxFiles) {
                        this.fileError = 'Можно прикрепить не более 10 файлов.';
                        this.fileLabel = 'файлы не выбраны';
                        input.value = '';
                        return;
                    }
                    const tooBig = files.filter((file) => file.size > this.maxBytes);
                    if (tooBig.length > 0) {
                        this.fileError = 'Файл «' + tooBig[0].name + '» больше 10 МБ. Уменьшите фото или выберите другой файл.';
                        this.fileLabel = 'файлы не выбраны';
                        input.value = '';
                        return;
                    }
                    this.fileLabel = files.map((file) => file.name).join(', ');
                }
            }">
                <label class="block text-sm font-medium mb-1" for="order-correspondence-attachments">Вложения</label>
                <div class="flex items-center gap-3">
                    <input type="file"
                           id="order-correspondence-attachments"
                           name="attachments[]"
                           multiple
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt"
                           class="sr-only"
                           @change="onFilesSelected($event)">
                    <label for="order-correspondence-attachments"
                           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                           title="Выбрать файлы"
                           aria-label="Выбрать файлы">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                    </label>
                    <span class="text-sm text-gray-500 dark:text-gray-400 truncate" x-text="fileLabel"></span>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">Фото, ТТН, акты, документы — до 10 файлов, каждый до 10 МБ.</p>
                <p x-show="fileError" x-cloak class="mt-1 text-xs text-[#c3242a]" x-text="fileError"></p>
                @error('attachments')
                    <p class="mt-1 text-xs text-[#c3242a]">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="mt-1 text-xs text-[#c3242a]">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="{{ $btnPrimary }}">Отправить</button>
            </div>
        </form>
    @endif
</section>

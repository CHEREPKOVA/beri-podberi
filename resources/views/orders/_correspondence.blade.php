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
            <div>
                <label class="block text-sm font-medium mb-1">Вложения</label>
                <input type="file"
                       name="attachments[]"
                       multiple
                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt"
                       class="block w-full text-sm text-gray-600 dark:text-gray-300
                              file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                              file:text-sm file:font-medium file:bg-[#c3242a] file:text-white
                              hover:file:bg-[#a01e24] file:cursor-pointer cursor-pointer">
                <p class="mt-1 text-[11px] text-gray-400">Фото, ТТН, акты, документы — до 10 файлов, каждый до 10 МБ.</p>
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

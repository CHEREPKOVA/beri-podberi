<div x-data="{ showAddForm: false, editingId: null, showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Контактные данные</h2>
        <button
            @click="showAddForm = true; editingId = null"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Добавить контакт
        </button>
    </div>

    {{-- Список контактов --}}
    <div class="space-y-4">
        @forelse($profile->contacts as $contact)
        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-5 bg-white dark:bg-gray-800/50" x-data="{ editing: false }">
            <div x-show="!editing">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-medium text-gray-900 dark:text-white">{{ $contact->full_name }}</h3>
                            @if($contact->is_primary)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                Основной
                            </span>
                            @endif
                        </div>
                        @if($contact->position)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $contact->position }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="editing = true"
                            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            title="Редактировать"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        @if($contact->canBeDeleted())
                        <button type="button"
                            @click="deleteFormAction = '{{ route('manufacturer.profile.contacts.delete', $contact) }}'; deleteMessage = {{ json_encode('Удалить контакт «' . $contact->full_name . '»?') }}; showDeleteModal = true"
                            class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            title="Удалить"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a href="mailto:{{ $contact->email }}" class="text-[#c3242a] hover:underline">{{ $contact->email }}</a>
                    </div>
                    @if($contact->phone)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <a href="tel:{{ $contact->phone }}" class="hover:underline">{{ $contact->phone }}</a>
                    </div>
                    @endif
                    @if($contact->department)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        {{ $contact->department }}
                    </div>
                    @endif
                </div>
                @if($contact->notes)
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2">{{ $contact->notes }}</p>
                @endif
            </div>

            {{-- Форма редактирования --}}
            <form x-show="editing" method="POST" action="{{ route('manufacturer.profile.contacts.update', $contact) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">ФИО <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" value="{{ $contact->full_name }}" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Должность</label>
                        <input type="text" name="position" value="{{ $contact->position }}" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ $contact->email }}" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Телефон</label>
                        <input type="text" name="phone" value="{{ $contact->phone }}" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Отдел</label>
                        <input type="text" name="department" value="{{ $contact->department }}" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="Отдел продаж, Техподдержка...">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Примечание</label>
                        <input type="text" name="notes" value="{{ $contact->notes }}" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs">
                        Сохранить
                    </button>
                    <button type="button" @click="editing = false" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-theme-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                        Отменить
                    </button>
                </div>
            </form>
        </div>
        @empty
        <div class="text-center py-12 text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Контакты не добавлены</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Добавьте контактные данные для связи с вашей компанией</p>
        </div>
        @endforelse
    </div>

    {{-- Модальное окно подтверждения удаления контакта --}}
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление контакта</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
            <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
            </form>
        </div>
    </div>

    {{-- Модальное окно добавления контакта --}}
    <div x-show="showAddForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showAddForm = false">
        <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-xl" @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Добавить контакт</h3>
            </div>
            <form method="POST" action="{{ route('manufacturer.profile.contacts.store') }}" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">ФИО <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Должность</label>
                        <input type="text" name="position" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Телефон</label>
                        <input type="text" name="phone" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Отдел</label>
                        <input type="text" name="department" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="Отдел продаж, Техподдержка...">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Примечание</label>
                        <input type="text" name="notes" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                </div>
                <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 mt-6">
                    <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs">
                        Добавить
                    </button>
                    <button type="button" @click="showAddForm = false" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-theme-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                        Отменить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

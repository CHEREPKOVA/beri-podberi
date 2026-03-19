<div x-data="{ showAddForm: false, showDeleteModal: false, deleteFormAction: '', deleteMessage: '' }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Склады</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Управление физическими складами производителя</p>
        </div>
        <div class="flex items-center gap-3">
            <a
                href="{{ route('manufacturer.warehouses.export') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-theme-xs dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Экспорт в CSV
            </a>
            <button
                @click="showAddForm = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Добавить склад
            </button>
        </div>
    </div>

    {{-- Список складов --}}
    <div class="space-y-4">
        @forelse($profile->warehouses as $warehouse)
        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-5 bg-white dark:bg-gray-800/50" x-data="{ editing: false }">
            <div x-show="!editing">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-medium text-gray-900 dark:text-white">{{ $warehouse->name }}</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $warehouse->type === 'main' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                {{ $warehouse->type === 'temporary' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                {{ $warehouse->type === 'transit' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' : '' }}
                            ">
                                {{ $warehouse->typeLabel() }}
                            </span>
                            @if(!$warehouse->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400" title="Склад скрыт из каталога и не показывается покупателям">
                                Скрыт из каталога
                            </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $warehouse->address }}</p>
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
                        <button type="button"
                            @click="deleteFormAction = '{{ route('manufacturer.warehouses.delete', $warehouse) }}'; deleteMessage = {{ json_encode('Удалить склад «' . $warehouse->name . '»? Убедитесь, что с ним не связаны активные заказы.') }}; showDeleteModal = true"
                            class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            title="Удалить"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    @if($warehouse->region)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $warehouse->region->name }}
                    </div>
                    @endif
                    @if($warehouse->responsible_person)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ $warehouse->responsible_person }}
                    </div>
                    @endif
                    @if($warehouse->phone)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <a href="tel:{{ $warehouse->phone }}" class="hover:underline">{{ $warehouse->phone }}</a>
                    </div>
                    @endif
                    @if($warehouse->working_hours)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $warehouse->working_hours }}
                    </div>
                    @endif
                </div>
                @if($warehouse->notes)
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2">{{ $warehouse->notes }}</p>
                @endif
            </div>

            {{-- Форма редактирования --}}
            <form x-show="editing" method="POST" action="{{ route('manufacturer.warehouses.update', $warehouse) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Название <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ $warehouse->name }}" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Тип склада <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="type" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                @foreach(\App\Models\Warehouse::typeLabels() as $value => $label)
                                <option value="{{ $value }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" {{ $warehouse->type === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Адрес <span class="text-red-500">*</span></label>
                        <input type="text" name="address" value="{{ $warehouse->address }}" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Регион</label>
                        <div class="relative">
                            <select name="region_id" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Не указан</option>
                                @foreach($regions as $region)
                                <option value="{{ $region->id }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400" {{ $warehouse->region_id == $region->id ? 'selected' : '' }}>{{ $region->name }}</option>
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
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Ответственный</label>
                        <input type="text" name="responsible_person" value="{{ $warehouse->responsible_person }}" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Телефон</label>
                        <input type="text" name="phone" value="{{ $warehouse->phone }}" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">График работы (для самовывоза)</label>
                        <input type="text" name="working_hours" value="{{ $warehouse->working_hours }}" placeholder="Например: Пн–Пт 9:00–18:00" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="flex items-center gap-3 h-11 mt-6">
                            <input type="checkbox" name="is_active" value="1" {{ $warehouse->is_active ? 'checked' : '' }} class="h-5 w-5 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Отображать в каталоге (склад виден покупателям)</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Примечание</label>
                        <textarea name="notes" rows="2" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 resize-none">{{ $warehouse->notes }}</textarea>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Склады не добавлены</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Добавьте информацию о складах вашей компании</p>
        </div>
        @endforelse
    </div>

    {{-- Модальное окно подтверждения удаления склада --}}
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление склада</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
            <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Отмена</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
            </form>
        </div>
    </div>

    {{-- Модальное окно добавления склада --}}
    <div x-show="showAddForm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showAddForm = false">
        <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-xl" @click.stop>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Добавить склад</h3>
            </div>
            <form method="POST" action="{{ route('manufacturer.warehouses.store') }}" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Название <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Тип склада <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="type" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                @foreach(\App\Models\Warehouse::typeLabels() as $value => $label)
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
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Адрес <span class="text-red-500">*</span></label>
                        <input type="text" name="address" required class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Регион</label>
                        <div class="relative">
                            <select name="region_id" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">Не указан</option>
                                @foreach($regions as $region)
                                <option value="{{ $region->id }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $region->name }}</option>
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
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Ответственный</label>
                        <input type="text" name="responsible_person" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Телефон</label>
                        <input type="text" name="phone" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">График работы (для самовывоза)</label>
                        <input type="text" name="working_hours" placeholder="Например: Пн–Пт 9:00–18:00" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Примечание</label>
                        <textarea name="notes" rows="2" class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 resize-none"></textarea>
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

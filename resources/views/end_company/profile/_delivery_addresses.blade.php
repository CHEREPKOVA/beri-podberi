<div x-data="{ showAdd: false, showDelete: false, deleteAction: '', deleteMsg: '' }">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Адреса доставки</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Склады, магазины, филиалы — точки получения заказов. Регион адреса по умолчанию определяет, какие товары и поставщики видны в каталоге.</p>
            @php $catalogRegion = auth()->user()->currentCompanyRegionName(); @endphp
            @if($catalogRegion)
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">Текущий регион каталога: <span class="font-medium">{{ $catalogRegion }}</span></p>
            @else
                <p class="text-sm text-amber-700 dark:text-amber-400 mt-2">Добавьте адрес с регионом и отметьте его по умолчанию — без этого каталог будет пустым.</p>
            @endif
        </div>
        <button type="button" @click="showAdd = true" class="px-4 py-2 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24]">Добавить адрес</button>
    </div>

    <div class="space-y-4">
        @forelse($profile->deliveryAddresses as $addr)
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-5" x-data="{ editing: false }">
                <div x-show="!editing" class="flex justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-medium text-gray-900 dark:text-white">{{ $addr->name }}</h3>
                            @if($addr->is_default)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">По умолчанию</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $addr->address }}</p>
                        <div class="mt-2 text-sm text-gray-500 space-y-1">
                            @if($addr->region) <span>{{ $addr->region->name }}</span> @endif
                            @if($addr->contact_person) <span> · {{ $addr->contact_person }}</span> @endif
                            @if($addr->phone) <span> · {{ $addr->phone }}</span> @endif
                            @if($addr->working_hours) <span class="block text-xs mt-1">График: {{ $addr->working_hours }}</span> @endif
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        @if(!$addr->is_default)
                            <form method="POST" action="{{ route('end_company.profile.delivery_addresses.default', $addr) }}">
                                @csrf
                                <button type="submit" class="text-xs text-[#c3242a] hover:underline">Сделать по умолчанию</button>
                            </form>
                        @endif
                        <button type="button" @click="editing = true" class="text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">Изменить</button>
                        <button type="button" @click="deleteAction = '{{ route('end_company.profile.delivery_addresses.delete', $addr) }}'; deleteMsg = {{ json_encode('Удалить адрес «'.$addr->name.'»?') }}; showDelete = true" class="text-sm text-red-600 hover:underline">Удалить</button>
                    </div>
                </div>

                <form x-show="editing" method="POST" action="{{ route('end_company.profile.delivery_addresses.update', $addr) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Название точки</label>
                            <input type="text" name="name" value="{{ $addr->name }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Регион</label>
                            <select name="region_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                <option value="">—</option>
                                @foreach($regions as $r)
                                    <option value="{{ $r->id }}" {{ $addr->region_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">Адрес</label>
                            <input type="text" name="address" value="{{ $addr->address }}" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Контактное лицо</label>
                            <input type="text" name="contact_person" value="{{ $addr->contact_person }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Телефон</label>
                            <input type="text" name="phone" value="{{ $addr->phone }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-500">График работы</label>
                            <input type="text" name="working_hours" value="{{ $addr->working_hours }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        </div>
                        <label class="sm:col-span-2 flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_default" value="1" {{ $addr->is_default ? 'checked' : '' }} class="rounded border-gray-300 accent-[#c3242a]">
                            Адрес по умолчанию
                        </label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white text-sm rounded-lg">Сохранить</button>
                        <button type="button" @click="editing = false" class="px-4 py-2 border border-gray-300 text-sm rounded-lg dark:border-gray-600">Отмена</button>
                    </div>
                </form>
            </div>
        @empty
            <p class="text-gray-500 text-sm">Адреса пока не добавлены.</p>
        @endforelse
    </div>

    {{-- Добавление --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showAdd = false">
        <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
            <h3 class="text-lg font-semibold mb-4">Новый адрес доставки</h3>
            <form method="POST" action="{{ route('end_company.profile.delivery_addresses.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-xs text-gray-500">Название точки <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Адрес <span class="text-red-500">*</span></label>
                    <input type="text" name="address" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Регион</label>
                    <select name="region_id" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="">—</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500">Контакт</label>
                        <input type="text" name="contact_person" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Телефон</label>
                        <input type="text" name="phone" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-500">График</label>
                    <input type="text" name="working_hours" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_default" value="1" class="rounded accent-[#c3242a]">
                    Сразу сделать адресом по умолчанию
                </label>
                <div class="flex gap-2 pt-2">
                    <button type="submit" class="px-4 py-2 bg-[#c3242a] text-white text-sm rounded-lg">Добавить</button>
                    <button type="button" @click="showAdd = false" class="px-4 py-2 border text-sm rounded-lg dark:border-gray-600">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDelete = false">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl p-6" @click.stop>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4" x-text="deleteMsg"></p>
            <form :action="deleteAction" method="POST" class="flex justify-end gap-2">
                @csrf
                @method('DELETE')
                <button type="button" @click="showDelete = false" class="px-3 py-2 text-sm text-gray-600">Отмена</button>
                <button type="submit" class="px-3 py-2 text-sm bg-red-600 text-white rounded-lg">Удалить</button>
            </form>
        </div>
    </div>
</div>

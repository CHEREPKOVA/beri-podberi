@extends('layouts.app')

@section('title', 'Карточка компании')
@section('heading', 'Карточка компании: ' . $company->name)

@section('content')
<div x-data="{ activeTab: '{{ $tab }}' }" class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
            @if(session('temporary_password'))
                <div class="mt-2 text-sm">Временный пароль: <span class="font-semibold">{{ session('temporary_password') }}</span></div>
            @endif
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex flex-wrap -mb-px">
                @php
                    $tabs = ['company' => 'Профиль компании'];
                    if ($companyProfile) {
                        $tabs['contacts'] = 'Контакты';
                        $tabs['regions'] = 'Регионы';
                        $tabs['delivery'] = 'Доставка';
                        $tabs['documents'] = 'Документы';
                        $tabs['warehouses'] = 'Склады';
                    }
                    $tabs['users'] = 'Пользователи';
                    $tabs['activity'] = 'Журнал';
                @endphp
                @foreach($tabs as $key => $label)
                    <button
                        @click="activeTab = '{{ $key }}'; window.history.replaceState({}, '', '?tab={{ $key }}')"
                        :class="activeTab === '{{ $key }}' ? 'border-[#c3242a] text-[#c3242a]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="p-6">
            <div x-show="activeTab === 'company'" x-cloak>
                <h2 class="text-lg font-semibold mb-4">Профиль компании (модерация)</h2>
                @php
                    $primaryContact = $companyProfile?->contacts?->firstWhere('is_primary', true);
                    $primaryRegion = $companyProfile?->regions?->firstWhere('pivot.is_primary', true);
                @endphp
                <form method="POST" action="{{ route('admin.companies.update', $companyKey) }}" class="grid md:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm mb-1">Название</label>
                        <input type="text" value="{{ $companyProfile?->full_name ?? $company->name }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Тип</label>
                        <input type="text" value="{{ config('roles.labels.' . $company->type, $company->type) }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Статус</label>
                        <div class="relative">
                            <select name="status" class="w-full appearance-none pl-3 pr-8 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                                @foreach($statusOptions as $status)
                                    <option value="{{ $status }}" {{ $company->status === $status ? 'selected' : '' }}>
                                        {{ $status === 'active' ? 'Активна' : ($status === 'pending' ? 'На модерации' : 'Заблокирована') }}
                                    </option>
                                @endforeach
                            </select>
                            <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Регион</label>
                        <input type="text" name="region" value="{{ old('region', $company->region ?? $primaryRegion?->name) }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Юридическое название</label>
                        <input type="text" name="legal_name" value="{{ old('legal_name', $company->legal_name ?? $companyProfile?->full_name) }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Контактный email</label>
                        <input type="email" name="contact_email" value="{{ old('contact_email', $company->contact_email ?? $primaryContact?->email) }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Контактный телефон</label>
                        <input type="text" name="contact_phone" value="{{ old('contact_phone', $company->contact_phone ?? $primaryContact?->phone) }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                    </div>
                    @if($companyProfile)
                    <div>
                        <label class="block text-sm mb-1">ИНН</label>
                        <input type="text" value="{{ $companyProfile->inn }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">КПП</label>
                        <input type="text" value="{{ $companyProfile->kpp ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">ОГРН / ОГРНИП</label>
                        <input type="text" value="{{ $companyProfile->ogrn ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Юридическая форма</label>
                        <input type="text" value="{{ $companyProfile->legalFormLabel() }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Юридический адрес</label>
                        <input type="text" value="{{ $companyProfile->legal_address ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Фактический адрес</label>
                        <input type="text" value="{{ $companyProfile->actual_address ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Банк</label>
                        <input type="text" value="{{ $companyProfile->bank_name ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">БИК</label>
                        <input type="text" value="{{ $companyProfile->bik ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Расчётный счёт</label>
                        <input type="text" value="{{ $companyProfile->checking_account ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Корреспондентский счёт</label>
                        <input type="text" value="{{ $companyProfile->correspondent_account ?: '—' }}" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Описание компании</label>
                        <textarea rows="4" disabled class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 text-sm">{{ $companyProfile->description ?: '—' }}</textarea>
                    </div>
                    @endif
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Служебные параметры</label>
                        <textarea name="service_params" rows="3" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">{{ old('service_params', data_get(json_decode($company->params ?? '{}', true), 'service_params')) }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#c3242a] text-white text-sm">Сохранить изменения</button>
                    </div>
                </form>
            </div>

            @if($companyProfile)
                <div x-show="activeTab === 'contacts'" x-cloak>
                    <h2 class="text-lg font-semibold mb-4">Контакты компании</h2>
                    <div class="space-y-3">
                        @forelse($companyProfile->contacts as $contact)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <div class="font-medium">{{ $contact->full_name }}</div>
                                <div class="text-sm text-gray-500">{{ $contact->position ?: '—' }}</div>
                                <div class="mt-2 text-sm">{{ $contact->email }} @if($contact->phone) | {{ $contact->phone }} @endif</div>
                                @if($contact->department || $contact->notes)
                                    <div class="mt-1 text-xs text-gray-500">{{ $contact->department ?: '' }} {{ $contact->notes ? ' | ' . $contact->notes : '' }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">Контакты не заполнены.</div>
                        @endforelse
                    </div>
                </div>

                <div x-show="activeTab === 'regions'" x-cloak>
                    <h2 class="text-lg font-semibold mb-4">Регионы присутствия</h2>
                    <div class="flex flex-wrap gap-2">
                        @forelse($companyProfile->regions as $region)
                            <span class="px-3 py-1.5 rounded-lg text-sm {{ $region->pivot->is_primary ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $region->name }}{{ $region->pivot->is_primary ? ' (основной)' : '' }}
                            </span>
                        @empty
                            <div class="text-sm text-gray-500">Регионы не выбраны.</div>
                        @endforelse
                    </div>
                </div>

                <div x-show="activeTab === 'delivery'" x-cloak>
                    <h2 class="text-lg font-semibold mb-4">Доставка и логистика</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <div class="text-sm font-medium mb-2">Способы доставки</div>
                            <div class="space-y-2">
                                @forelse($deliveryMethods as $method)
                                    <div class="text-sm {{ $companyProfile->deliveryMethods->contains($method->id) ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ $method->name }}{!! $companyProfile->deliveryMethods->contains($method->id) ? ' <span class="text-green-600">- подключено</span>' : '' !!}
                                    </div>
                                @empty
                                    <div class="text-sm text-gray-500">Нет данных.</div>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium mb-2">Транспортные компании</div>
                            <div class="space-y-2">
                                @forelse($transportCompanies as $transport)
                                    <div class="text-sm {{ $companyProfile->transportCompanies->contains($transport->id) ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ $transport->name }}{!! $companyProfile->transportCompanies->contains($transport->id) ? ' <span class="text-green-600">- подключена</span>' : '' !!}
                                    </div>
                                @empty
                                    <div class="text-sm text-gray-500">Нет данных.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-sm font-medium mb-1">Особые условия</div>
                        <div class="text-sm text-gray-600">{{ $companyProfile->delivery_notes ?: '—' }}</div>
                    </div>
                </div>

                <div x-show="activeTab === 'documents'" x-cloak>
                    <h2 class="text-lg font-semibold mb-4">Документы</h2>
                    <div class="space-y-3">
                        @forelse($companyProfile->documents as $document)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ $document->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $document->typeLabel() }} | {{ $document->file_size_formatted }}</div>
                                </div>
                                <a href="{{ $document->url }}" target="_blank" class="text-sm text-[#c3242a] hover:underline">Открыть</a>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">Документы не загружены.</div>
                        @endforelse
                    </div>
                </div>

                <div x-show="activeTab === 'warehouses'" x-cloak>
                    <h2 class="text-lg font-semibold mb-4">Склады</h2>
                    <div class="space-y-3">
                        @forelse($companyProfile->warehouses as $warehouse)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <div class="font-medium">{{ $warehouse->name }} <span class="text-xs text-gray-500">({{ $warehouse->typeLabel() }})</span></div>
                                <div class="text-sm text-gray-600">{{ $warehouse->address }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $warehouse->region?->name ?: 'Регион не указан' }}
                                    @if($warehouse->phone) | {{ $warehouse->phone }} @endif
                                    @if($warehouse->working_hours) | {{ $warehouse->working_hours }} @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">Склады не заполнены.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            <div x-show="activeTab === 'users'" x-cloak>
                <h2 class="text-lg font-semibold mb-4">Пользователи компании</h2>
                <div class="space-y-4">
                    @forelse($employees as $employee)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <form method="POST" action="{{ route('admin.companies.users.update', [$companyKey, $employee]) }}" class="grid md:grid-cols-4 gap-3 items-end">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="block text-xs mb-1">Имя</label>
                                    <input type="text" name="name" value="{{ $employee->name }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1">Email</label>
                                    <input type="email" name="email" value="{{ $employee->email }}" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1">Роль</label>
                                    @php $currentRoleId = $employee->roles->first()?->id; @endphp
                                    <div class="relative">
                                        <select name="role_id" class="w-full appearance-none pl-3 pr-8 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer">
                                            @foreach($roleOptions as $role)
                                                <option value="{{ $role->id }}" {{ (int) $currentRoleId === (int) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="px-3 py-2 rounded-lg bg-gray-100 text-sm">Сохранить</button>
                                </div>
                            </form>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if($employee->is_active)
                                    <form method="POST" action="{{ route('admin.companies.users.suspend', [$companyKey, $employee]) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-700 text-xs">Заблокировать</button></form>
                                @else
                                    <form method="POST" action="{{ route('admin.companies.users.activate', [$companyKey, $employee]) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-green-100 text-green-700 text-xs">Разблокировать</button></form>
                                @endif
                                <form method="POST" action="{{ route('admin.companies.users.reset-password', [$companyKey, $employee]) }}">@csrf<button class="px-3 py-1.5 rounded-lg bg-blue-100 text-blue-700 text-xs">Сбросить пароль</button></form>
                                <form method="POST" action="{{ route('admin.companies.users.delete', [$companyKey, $employee]) }}" onsubmit="return confirm('Удалить пользователя из компании?');">@csrf @method('DELETE')<button class="px-3 py-1.5 rounded-lg bg-red-100 text-red-700 text-xs">Удалить</button></form>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Сотрудников нет.</div>
                    @endforelse
                </div>
            </div>

            <div x-show="activeTab === 'activity'" x-cloak>
                <h2 class="text-lg font-semibold mb-4">Журнал действий</h2>
                <div class="space-y-2">
                    @forelse($activity as $log)
                        <div class="text-sm text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700 pb-2">
                            <div class="font-medium">{{ $log->action }}</div>
                            <div class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d.m.Y H:i') }} — {{ $log->admin_name }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Действий пока нет.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

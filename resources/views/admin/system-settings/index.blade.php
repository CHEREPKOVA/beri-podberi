@extends('layouts.app')

@section('title', 'Глобальные настройки')
@section('heading', 'Глобальные настройки системы')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')

    <a href="{{ route('admin.directories.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Справочники</a>

    <form method="POST" action="{{ route('admin.system-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @forelse($settings as $groupKey => $groupSettings)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::headline($groupKey) }}</h2>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($groupSettings as $setting)
                        <div class="p-6 grid md:grid-cols-12 gap-4 items-start">
                            <div class="md:col-span-4">
                                <label class="block text-sm font-medium text-gray-800 dark:text-gray-200">{{ $setting->label }}</label>
                                @if($setting->description)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $setting->description }}</p>
                                @endif
                                <p class="mt-1 text-[11px] text-gray-400 font-mono">{{ $setting->key }}</p>
                            </div>

                            <div class="md:col-span-5">
                                @if($setting->value_type === 'boolean')
                                    <input type="hidden" name="settings[{{ $setting->id }}][value]" value="0" />
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="settings[{{ $setting->id }}][value]" value="1" @checked((string) $setting->value === '1')
                                            class="h-4 w-4 shrink-0 rounded border-gray-300 bg-white dark:bg-gray-700 accent-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/50 focus:ring-offset-0 dark:accent-[#c3242a]" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Включено</span>
                                    </label>
                                @elseif($setting->value_type === 'integer')
                                    <input type="number" name="settings[{{ $setting->id }}][value]" value="{{ $setting->value }}"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                                @elseif($setting->value_type === 'float')
                                    <input type="number" step="0.01" name="settings[{{ $setting->id }}][value]" value="{{ $setting->value }}"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                                @else
                                    <input type="text" name="settings[{{ $setting->id }}][value]" value="{{ $setting->value }}"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm" />
                                @endif
                            </div>

                            <div class="md:col-span-3">
                                <input type="hidden" name="settings[{{ $setting->id }}][is_active]" value="0" />
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="settings[{{ $setting->id }}][is_active]" value="1" @checked($setting->is_active)
                                        class="h-4 w-4 shrink-0 rounded border-gray-300 bg-white dark:bg-gray-700 accent-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/50 focus:ring-offset-0 dark:accent-[#c3242a]" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Активен</span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500">
                Настройки пока не созданы.
            </div>
        @endforelse

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 bg-[#c3242a] text-white rounded-lg hover:bg-[#a01e24] text-sm font-medium">
                Сохранить изменения
            </button>
        </div>
    </form>
</div>
@endsection

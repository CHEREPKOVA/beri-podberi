@extends('layouts.app')

@section('title', 'Импорт номенклатуры')
@section('heading', 'Импорт номенклатуры')

@section('content')
<div class="max-w-2xl space-y-6">
    <a href="{{ route('distributor.products.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Назад к номенклатуре
    </a>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif
    @if(session('import_errors'))
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm">
        <p class="font-medium mb-2">Замечания при импорте:</p>
        <ul class="list-disc list-inside space-y-1">
            @foreach(session('import_errors') as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">CSV / YML</h2>
            <p class="text-sm text-gray-500 mt-1">
                Обновление цен и остатков по внутреннему артикулу существующих позиций номенклатуры.
                CSV: разделители «;» и «,», UTF-8 и Windows-1251. YML: формат Яндекс.Маркет.
                Новые товары через импорт не создаются — сначала добавьте позицию из каталога производителя.
            </p>
            <p class="text-xs text-gray-400 mt-2 font-mono">internal_sku;retail_price;purchase_price;quantity</p>
            <p class="text-xs text-gray-400 mt-1">Колонки CSV: внутренний артикул (обяз.), цена, закупочная, остаток.</p>
        </div>

        <form method="POST" action="{{ route('distributor.products.import.process') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Тип импорта</label>
                <select name="import_type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    <option value="full">Цены и остатки</option>
                    <option value="prices">Только цены</option>
                    <option value="stocks">Только остатки</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Файл CSV или YML</label>
                <input type="file" name="file" accept=".csv,.txt,.xml,.yml,.yaml" required class="w-full text-sm" />
            </div>
            <button type="submit" class="px-6 py-2 bg-[#c3242a] text-white rounded-lg text-sm font-medium hover:bg-[#a01e24]">Загрузить</button>
        </form>

        <hr class="border-gray-200 dark:border-gray-700" />

        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">YML по ссылке</h2>
            <p class="text-sm text-gray-500 mt-1">
                @if($profile->integration_yml_enabled && $profile->integration_yml_feed_url)
                Настроена ссылка: <span class="font-mono text-xs">{{ $profile->integration_yml_feed_url }}</span>
                @else
                Укажите URL фида в <a href="{{ route('distributor.profile', ['tab' => 'integration']) }}" class="text-[#c3242a] hover:underline">настройках интеграции</a>.
                @endif
            </p>
        </div>

        <hr class="border-gray-200 dark:border-gray-700" />

        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">1С</h2>
            <p class="text-sm text-gray-500 mt-1">
                @if($profile->integration_import_1c_stocks)
                Обмен с 1С включён. Остатки и цены обновляются автоматически; ручное редактирование ограничено.
                @else
                Подключите обмен в <a href="{{ route('distributor.profile', ['tab' => 'integration']) }}" class="text-[#c3242a] hover:underline">профиле</a>.
                @endif
            </p>
        </div>
    </div>
</div>
@endsection

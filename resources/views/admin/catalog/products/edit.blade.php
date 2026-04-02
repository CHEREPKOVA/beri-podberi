@extends('layouts.app')

@section('title', 'Редактирование товара')
@section('heading', 'Редактирование товара')

@section('content')
<div class="space-y-6">
    @include('admin.partials.flash')
    <a href="{{ route('admin.catalog.products.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← Товары</a>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <p class="text-sm text-gray-500 mb-4">
            Коммерческие параметры (цены, остатки) в этом разделе не редактируются.
        </p>

        <form method="POST" action="{{ route('admin.catalog.products.update', $product) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.partials.form-errors')

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm mb-2">Наименование</label>
                    <input type="text" name="name" required value="{{ old('name', $product->name) }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
                    @include('admin.partials.field-error', ['field' => 'name'])
                </div>
                <div>
                    <label class="block text-sm mb-2">SKU</label>
                    <input type="text" value="{{ $product->sku }}" disabled class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-100 dark:bg-gray-900 text-gray-500" />
                </div>
                <div>
                    <label class="block text-sm mb-2">Основная категория</label>
                    <select name="category_id" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                        <option value="">Не выбрана</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>
                            {{ $category->full_path }}
                        </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['field' => 'category_id'])
                </div>
                <div>
                    <label class="block text-sm mb-2">Дополнительные категории</label>
                    <select name="additional_category_ids[]" multiple class="w-full min-h-[110px] px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array($category->id, old('additional_category_ids', $product->additionalCategories->pluck('id')->all())))>
                            {{ $category->full_path }}
                        </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['field' => 'additional_category_ids'])
                </div>
                <div>
                    <label class="block text-sm mb-2">Статус</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                        @foreach(\App\Models\Product::statusLabels() as $status => $label)
                        <option value="{{ $status }}" @selected(old('status', $product->status) === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['field' => 'status'])
                </div>
                <label class="inline-flex items-center gap-2 text-sm mt-8">
                    <input type="checkbox" name="show_in_catalog" value="1" @checked(old('show_in_catalog', $product->show_in_catalog)) />
                    Показывать в каталоге
                </label>
            </div>

            <div>
                <label class="block text-sm mb-2">Описание</label>
                <textarea name="description" rows="6" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">{{ old('description', $product->description) }}</textarea>
                @include('admin.partials.field-error', ['field' => 'description'])
            </div>

            <div class="flex justify-end">
                <button class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить изменения</button>
            </div>
        </form>
    </div>
</div>
@endsection

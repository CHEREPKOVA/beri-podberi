<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Наименование товара <span class="text-red-500">*</span>
            </label>
            <input type="text" name="name" value="{{ old('name', $product?->name) }}" required
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Артикул / SKU <span class="text-red-500">*</span>
            </label>
            <input type="text" name="sku" value="{{ old('sku', $product?->sku) }}" required
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Основная категория <span class="text-red-500">*</span>
            </label>
            @include('manufacturer.products._category_tree_select', [
                'name' => 'category_id',
                'tree' => $categoryTree,
                'categories' => $categories,
                'selectedId' => $product?->category_id,
                'placeholder' => 'Выберите категорию',
                'inputId' => 'product-main-category',
                'notifyCategoryChange' => true,
            ])
            <div
                x-show="categoryPendingSave"
                x-cloak
                class="mt-2 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
            >
                <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p>
                    Категория изменена. Нажмите «Сохранить» — после этого на вкладке
                    <button type="button" @click="activeTab = 'attributes'" class="font-medium text-[#c3242a] hover:underline">«Характеристики»</button>
                    обновится набор полей под новую категорию.
                </p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Единица измерения</label>
            @include('manufacturer.products._flat_select', [
                'name' => 'unit_type_id',
                'options' => $unitTypes->mapWithKeys(fn ($unit) => [
                    $unit->id => $unit->name . ' (' . $unit->short_name . ')',
                ])->all(),
                'selected' => $product?->unit_type_id,
                'placeholder' => 'Выберите единицу',
                'inputId' => 'product-unit-type',
                'allowClear' => true,
                'clearLabel' => 'Не выбрано',
            ])
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Дополнительные категории</label>
        <p class="text-xs text-gray-500 mb-2">Для аналогов и вспомогательной фильтрации. Основная категория не дублируется.</p>
        @include('manufacturer.products._category_tree_select', [
            'name' => 'additional_category_ids',
            'tree' => $categoryTree,
            'categories' => $categories,
            'selectedIds' => $product?->additionalCategories->pluck('id')->toArray() ?? [],
            'multiple' => true,
            'placeholder' => 'Выберите дополнительные категории',
            'inputId' => 'product-additional-categories',
        ])
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Описание <span class="text-xs text-gray-500">(до 2000 символов)</span>
        </label>
        <textarea name="description" rows="5" maxlength="2000"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent">{{ old('description', $product?->description) }}</textarea>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Ссылка на видеообзор <span class="text-xs text-gray-500">(Rutube, VK)</span>
            </label>
            <input type="url" name="video_url" value="{{ old('video_url', $product?->video_url) }}"
                placeholder="https://..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Минимальное количество заказа
            </label>
            <input type="number" name="min_order_quantity" value="{{ old('min_order_quantity', $product?->min_order_quantity) }}"
                min="1" step="1"
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-[#c3242a] focus:border-transparent" />
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Фотографии товара <span class="text-xs text-gray-500">(до 5 изображений)</span>
        </label>

        @if($product && $product->images->count() > 0)
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
            @foreach($product->images as $image)
            <div class="relative group">
                <img src="{{ $image->url }}" alt="" class="w-full h-32 object-cover rounded-lg border-2 {{ $image->is_primary ? 'border-[#c3242a]' : 'border-gray-200' }}" />
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                    @if(!$image->is_primary)
                    <button type="submit" form="set-primary-image-{{ $image->id }}"
                        class="p-1.5 bg-white rounded-full text-gray-700 hover:text-[#c3242a]" title="Сделать основным">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </button>
                    @endif
                    <button type="button"
                        @click="$dispatch('open-aux-delete', { action: '{{ route('manufacturer.products.image.delete', $image) }}', message: 'Удалить это изображение?' })"
                        class="p-1.5 bg-white rounded-full text-gray-700 hover:text-red-600"
                        title="Удалить">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
                @if($image->is_primary)
                <span class="absolute top-1 left-1 px-2 py-0.5 bg-[#c3242a] text-white text-xs rounded">Основное</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
            <input type="file" name="images[]" id="product-images" multiple accept="image/*" class="hidden"
                @change="
                    const dt = new DataTransfer();
                    for (let file of $event.target.files) {
                        dt.items.add(file);
                    }
                " />
            <label for="product-images" class="cursor-pointer">
                <svg class="mx-auto w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Нажмите для загрузки изображений</p>
                <p class="text-xs text-gray-500">PNG, JPG, JPEG до 5 МБ</p>
            </label>
        </div>
    </div>
</div>

@if($product)
@push('product-external-forms')
    @foreach($product->images as $image)
        @if(!$image->is_primary)
        <form id="set-primary-image-{{ $image->id }}" action="{{ route('manufacturer.products.image.primary', $image) }}" method="POST" class="hidden">
            @csrf
        </form>
        @endif
    @endforeach
@endpush
@endif

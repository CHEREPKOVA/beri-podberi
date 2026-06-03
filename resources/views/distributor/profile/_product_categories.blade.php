@php
    $selectedIds = array_map(
        'intval',
        (array) old('category_ids', $profile->productCategories->pluck('id')->all()),
    );
@endphp

<div>
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Типы продукции</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Укажите категории товаров, с которыми работает ваша компания. Без выбора хотя бы одной категории профиль не отображается в каталоге партнёров производителей.
        </p>
    </div>

    <form method="POST" action="{{ route('distributor.profile.product_categories.update') }}">
        @csrf
        @method('PUT')

        @error('category_ids')
        <div class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
        @enderror
        @error('category_ids.*')
        <div class="mb-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
        @enderror

        <div class="mb-4 px-4 py-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Выбрано категорий: <span class="font-semibold text-gray-900 dark:text-white">{{ count($selectedIds) }}</span>
            </p>
        </div>

        <div class="space-y-8 max-h-[520px] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-white dark:bg-gray-800/30">
            @foreach($productCategoryRoots as $root)
            <section>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ $root->name }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @forelse($root->children as $category)
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                        <input
                            type="checkbox"
                            name="category_ids[]"
                            value="{{ $category->id }}"
                            @checked(in_array($category->id, $selectedIds, true))
                            class="h-4 w-4 rounded border-gray-300 accent-[#c3242a] text-[#c3242a] focus:ring-[#c3242a]"
                        >
                        <span class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
                    </label>
                    @empty
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                        <input
                            type="checkbox"
                            name="category_ids[]"
                            value="{{ $root->id }}"
                            @checked(in_array($root->id, $selectedIds, true))
                            class="h-4 w-4 rounded border-gray-300 accent-[#c3242a] text-[#c3242a] focus:ring-[#c3242a]"
                        >
                        <span class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ $root->name }}</span>
                    </label>
                    @endforelse
                </div>
            </section>
            @endforeach
        </div>

        <div class="mt-6">
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Сохранить изменения
            </button>
        </div>
    </form>
</div>

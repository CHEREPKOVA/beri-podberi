@php
    $selectedIds = array_map(
        'intval',
        (array) old('category_ids', $profile->productCategories->pluck('id')->all()),
    );

    $allCategoryIds = $productCategoryRoots
        ->flatMap(fn ($root) => $root->children->isNotEmpty() ? $root->children->pluck('id') : collect([$root->id]))
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all();

    $categoryGroups = $productCategoryRoots->map(function ($root) {
        $ids = $root->children->isNotEmpty()
            ? $root->children->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : [(int) $root->id];

        return [
            'name' => $root->name,
            'ids' => $ids,
            'categories' => $root->children->isNotEmpty()
                ? $root->children
                : collect([$root]),
        ];
    });
@endphp

<div x-data="{
    selectedCategories: @js($selectedIds),
    allCategoryIds: @js($allCategoryIds),
    toggleGroup(ids) {
        const allSelected = ids.every(id => this.selectedCategories.includes(id));
        if (allSelected) {
            this.selectedCategories = this.selectedCategories.filter(id => !ids.includes(id));
        } else {
            this.selectedCategories = [...new Set([...this.selectedCategories, ...ids])];
        }
    },
    isGroupSelected(ids) {
        return ids.length > 0 && ids.every(id => this.selectedCategories.includes(id));
    }
}">
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

        <div class="flex flex-wrap gap-2 mb-4">
            <button
                type="button"
                @click="selectedCategories = [...allCategoryIds]"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/30 transition"
            >
                Выбрать все
            </button>
            <button
                type="button"
                @click="selectedCategories = []"
                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
            >
                Очистить
            </button>
        </div>

        <div class="mb-4 px-4 py-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Выбрано категорий: <span class="font-semibold text-gray-900 dark:text-white" x-text="selectedCategories.length"></span>
            </p>
        </div>

        <div class="space-y-8 max-h-[520px] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-white dark:bg-gray-800/30">
            @foreach($categoryGroups as $group)
            <section>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $group['name'] }}</h3>
                    <button
                        type="button"
                        @click="toggleGroup(@js($group['ids']))"
                        class="px-2.5 py-1 text-xs font-medium rounded-lg border transition"
                        :class="isGroupSelected(@js($group['ids']))
                            ? 'border-[#c3242a] bg-red-50 text-[#c3242a] dark:border-[#c3242a] dark:bg-red-900/20 dark:text-red-400'
                            : 'border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'"
                    >
                        <span x-text="isGroupSelected(@js($group['ids'])) ? 'Снять группу' : 'Выбрать группу'"></span>
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($group['categories'] as $category)
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20">
                        <input
                            type="checkbox"
                            name="category_ids[]"
                            value="{{ $category->id }}"
                            x-model.number="selectedCategories"
                            class="h-4 w-4 rounded border-gray-300 accent-[#c3242a] text-[#c3242a] focus:ring-[#c3242a]"
                        >
                        <span class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
                    </label>
                    @endforeach
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

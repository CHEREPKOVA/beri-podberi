@if(!$product)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Сначала сохраните товар, затем сможете назначать аналоги.
    </div>
@else
    @php
        $preset = ($selectedAnalogs ?? collect())
            ->map(fn ($item) => ['id' => (int) $item->id, 'name' => (string) $item->name, 'sku' => (string) $item->sku])
            ->values()
            ->all();
    @endphp
    <div
        x-data="{
            search: '',
            loading: false,
            results: [],
            selected: {{ json_encode($preset, JSON_UNESCAPED_UNICODE) }},
            timer: null,
            doSearch() {
                if (this.timer) {
                    clearTimeout(this.timer);
                }
                if (this.search.trim().length < 2) {
                    this.results = [];
                    return;
                }
                this.timer = setTimeout(() => this.fetchResults(), 250);
            },
            async fetchResults() {
                this.loading = true;
                const url = `{{ route('manufacturer.products.analogs.search', $product) }}?q=${encodeURIComponent(this.search.trim())}`;
                try {
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const payload = await response.json();
                    const ids = new Set(this.selected.map((i) => i.id));
                    this.results = (payload.data || []).filter((item) => !ids.has(item.id));
                } finally {
                    this.loading = false;
                }
            },
            add(item) {
                if (this.selected.some((row) => row.id === item.id)) {
                    return;
                }
                this.selected.push(item);
                this.results = this.results.filter((row) => row.id !== item.id);
            },
            remove(id) {
                this.selected = this.selected.filter((item) => item.id !== id);
            }
        }"
        class="space-y-4"
    >
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Связи аналогов</h3>
            <p class="text-sm text-gray-500 mt-1">Поиск работает по названию, SKU и коду производителя. Введите минимум 2 символа.</p>
        </div>

        <div class="relative max-w-2xl">
            <input
                type="text"
                x-model="search"
                @input="doSearch"
                placeholder="Найти товар для добавления в аналоги..."
                class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent"
            />
            <div x-show="loading" x-cloak class="absolute right-3 top-2.5 text-xs text-gray-500">Поиск...</div>
        </div>

        <div x-show="results.length > 0" x-cloak class="border border-gray-200 dark:border-gray-700 rounded-lg max-h-72 overflow-y-auto">
            <template x-for="item in results" :key="item.id">
                <button
                    type="button"
                    @click="add(item)"
                    class="w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                >
                    <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="item.name"></span>
                    <span class="ml-2 text-xs text-gray-500" x-text="'(' + item.sku + ')'"></span>
                </button>
            </template>
        </div>

        <div class="space-y-2">
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Выбранные аналоги</p>
            <template x-if="selected.length === 0">
                <p class="text-sm text-gray-500">Аналоги не выбраны.</p>
            </template>
            <div class="space-y-2">
                <template x-for="item in selected" :key="item.id">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="item.name"></p>
                            <p class="text-xs text-gray-500" x-text="'SKU: ' + item.sku"></p>
                        </div>
                        <button type="button" @click="remove(item.id)" class="text-xs text-red-600 hover:text-red-700">Убрать</button>
                    </div>
                </template>
            </div>
        </div>

        <template x-for="item in selected" :key="'hidden-' + item.id">
            <input type="hidden" name="analog_ids[]" :value="item.id">
        </template>
    </div>
@endif

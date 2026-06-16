function catalogTreeMixin(initialCategorySlug, initialOpenSlugs) {
    return {
        selectedCategorySlug: initialCategorySlug || null,
        categoryOpen: initialOpenSlugs || {},
        catalogActiveLinkClasses: 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium',
        catalogInactiveLinkClasses: 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
        categoryLinkClass(slug) {
            const active = slug
                ? this.selectedCategorySlug === slug
                : !this.selectedCategorySlug;

            return (active ? this.catalogActiveLinkClasses : this.catalogInactiveLinkClasses) + ' block px-3 py-2 rounded-lg text-sm transition-colors';
        },
        categoryNodeLinkClass(slug) {
            const active = this.selectedCategorySlug === slug;

            return (active ? this.catalogActiveLinkClasses : this.catalogInactiveLinkClasses) + ' flex-1 px-2 py-1.5 rounded-lg text-sm transition-colors';
        },
        isCategoryOpen(slug) {
            return Boolean(this.categoryOpen[slug]);
        },
        toggleCategoryOpen(slug) {
            this.categoryOpen[slug] = !this.isCategoryOpen(slug);
        },
        collapseAll() {
            this.categoryOpen = {};
        },
        expandAncestorsForSlug(slug) {
            if (!slug) {
                return;
            }
            const el = document.querySelector('[data-category-slug="' + CSS.escape(slug) + '"]');
            if (!el) {
                return;
            }
            (el.dataset.ancestorSlugs || '').split(',').filter(Boolean).forEach((ancestorSlug) => {
                this.categoryOpen[ancestorSlug] = true;
            });
            if (el.dataset.hasChildren === '1') {
                this.categoryOpen[slug] = true;
            }
        },
    };
}

function catalogListingMixin(config) {
    return {
        productsContainerId: config.productsContainerId,
        productsFetchUrl: config.productsFetchUrl,
        baseCatalogUrl: config.baseCatalogUrl,
        catalogSearchSuggestUrl: config.catalogSearchSuggestUrl || '',
        catalogRegionSetUrl: config.catalogRegionSetUrl || '',
        searchMinQueryLength: Number(config.searchMinQueryLength || 2),
        filterApplyTimer: null,
        loading: false,
        buildCatalogParams(includeFilters, options = {}) {
            const structuralOnly = options.structuralOnly === true;
            const params = new URLSearchParams();
            if (this.selectedCategorySlug) {
                params.set('category', this.selectedCategorySlug);
            }
            const searchEl = document.getElementById('catalog-search-input');
            if (searchEl && searchEl.value.trim() !== '') {
                params.set('search', searchEl.value.trim());
            }
            const scopeEl = document.getElementById('catalog-search-scope-global');
            if (scopeEl) {
                if (scopeEl.type === 'checkbox') {
                    params.set('search_scope', scopeEl.checked ? 'global' : 'category');
                } else if (scopeEl.value) {
                    params.set('search_scope', scopeEl.value);
                }
            }
            if (includeFilters) {
                const form = document.getElementById('catalog-filters-form');
                if (form) {
                    new FormData(form).forEach((value, key) => {
                        if (structuralOnly && key.startsWith('attr[')) {
                            return;
                        }
                        if (value !== '' && value !== null) {
                            params.append(key, value);
                        }
                    });
                }
            }
            const page = new URLSearchParams(window.location.search).get('page');
            if (page && includeFilters) {
                params.set('page', page);
            }

            return params;
        },
        fetchCatalogProducts(params) {
            if (this.loading) {
                return;
            }
            this.loading = true;
            const url = this.productsFetchUrl + (params.toString() ? '?' + params.toString() : '');
            const slug = params.get('category');
            let catalogUrl = this.baseCatalogUrl;
            if (slug && slug !== '') {
                catalogUrl += '/' + encodeURIComponent(slug);
            }
            const displayParams = new URLSearchParams(params);
            displayParams.delete('category');
            if (displayParams.toString()) {
                catalogUrl += '?' + displayParams.toString();
            }
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then((r) => r.text())
                .then((html) => {
                    const container = document.getElementById(this.productsContainerId);
                    if (container) {
                        container.innerHTML = html;
                    }
                    history.replaceState({ category: slug || null }, '', catalogUrl);
                    this.selectedCategorySlug = slug || null;
                    if (slug) {
                        this.expandAncestorsForSlug(slug);
                    }
                    this.bindPaginationLinks();
                })
                .finally(() => { this.loading = false; });
        },
        bindPaginationLinks() {
            const container = document.getElementById(this.productsContainerId);
            if (!container) {
                return;
            }
            container.querySelectorAll('.catalog-pagination a').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const href = link.getAttribute('href');
                    if (!href) {
                        return;
                    }
                    const pageParams = new URLSearchParams(href.split('?')[1] || '');
                    const params = this.buildCatalogParams(true);
                    if (pageParams.get('page')) {
                        params.set('page', pageParams.get('page'));
                    }
                    this.fetchCatalogProducts(params);
                });
            });
        },
        loadCategory(slug) {
            this.selectedCategorySlug = slug || null;
            const params = this.buildCatalogParams(true, { structuralOnly: true });
            params.delete('page');
            if (slug) {
                params.set('category', slug);
            } else {
                params.delete('category');
            }
            this.fetchCatalogProducts(params);
        },
        applyCatalogFilters() {
            const params = this.buildCatalogParams(true);
            params.delete('page');
            this.fetchCatalogProducts(params);
        },
        applyCatalogFiltersDebounced() {
            clearTimeout(this.filterApplyTimer);
            this.filterApplyTimer = setTimeout(() => this.applyCatalogFilters(), 500);
        },
        resetCatalogFilters() {
            clearTimeout(this.filterApplyTimer);
            this.catalogFiltersOpen = false;
            const form = document.getElementById('catalog-filters-form');
            if (form) {
                form.reset();
            }
            const searchEl = document.getElementById('catalog-search-input');
            if (searchEl) {
                searchEl.value = '';
            }
            const scopeEl = document.getElementById('catalog-search-scope-global');
            if (scopeEl && scopeEl.type === 'checkbox') {
                scopeEl.checked = false;
            }
            this.closeSuggestions();
            this.loadCategory(this.selectedCategorySlug);
        },
        setCatalogRegion(regionId) {
            if (!this.catalogRegionSetUrl || !regionId) {
                return;
            }
            fetch(this.catalogRegionSetUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ region_id: parseInt(regionId, 10) }),
            }).then((r) => {
                if (r.ok) {
                    this.fetchCatalogProducts(this.buildCatalogParams(true));
                }
            });
        },
    };
}

function catalogSearchSuggestMixin(config) {
    return {
        catalogSearchSuggestUrl: config.catalogSearchSuggestUrl || '',
        suggestOpen: false,
        suggestLoading: false,
        suggestions: { products: [], categories: [], manufacturers: [], articles: [], popular: [] },
        showPopularSearches() {
            const q = document.getElementById('catalog-search-input')?.value?.trim() || '';
            return q.length < 2 && (this.suggestions.popular?.length || 0) > 0;
        },
        hasSuggestions() {
            const s = this.suggestions;
            return (s.products?.length || 0)
                || (s.categories?.length || 0)
                || (s.manufacturers?.length || 0)
                || (s.articles?.length || 0);
        },
        onSearchInput() {
            this.fetchSuggestions();
        },
        onSearchFocus() {
            const el = document.getElementById('catalog-search-input');
            if (el && el.value.trim().length >= this.searchMinQueryLength) {
                this.fetchSuggestions();
            } else {
                this.fetchPopularSearches();
            }
        },
        closeSuggestions() {
            this.suggestOpen = false;
        },
        buildSuggestParams() {
            const params = new URLSearchParams();
            const q = document.getElementById('catalog-search-input')?.value?.trim() || '';
            params.set('q', q);
            if (this.selectedCategorySlug) {
                params.set('category', this.selectedCategorySlug);
            }
            const scopeEl = document.getElementById('catalog-search-scope-global');
            if (scopeEl) {
                if (scopeEl.type === 'checkbox') {
                    params.set('search_scope', scopeEl.checked ? 'global' : 'category');
                } else if (scopeEl.value) {
                    params.set('search_scope', scopeEl.value);
                }
            }
            return params;
        },
        fetchSuggestions() {
            const suggestUrl = this.catalogSearchSuggestUrl;
            const q = document.getElementById('catalog-search-input')?.value?.trim() || '';
            if (!suggestUrl) {
                return;
            }
            if (q.length < this.searchMinQueryLength) {
                this.fetchPopularSearches();
                return;
            }
            this.suggestLoading = true;
            this.suggestOpen = true;
            this.suggestions = { products: [], categories: [], manufacturers: [], articles: [], popular: [] };
            const url = suggestUrl + '?' + this.buildSuggestParams().toString();
            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((r) => {
                    if (!r.ok) {
                        throw new Error('Suggest request failed');
                    }
                    return r.json();
                })
                .then((data) => {
                    this.suggestions = {
                        products: data.products || [],
                        categories: data.categories || [],
                        manufacturers: data.manufacturers || [],
                        articles: data.articles || [],
                        popular: data.popular || [],
                    };
                    this.suggestOpen = true;
                })
                .catch(() => {
                    this.suggestions = { products: [], categories: [], manufacturers: [], articles: [], popular: [] };
                    this.suggestOpen = true;
                })
                .finally(() => { this.suggestLoading = false; });
        },
        fetchPopularSearches() {
            const suggestUrl = this.catalogSearchSuggestUrl;
            if (!suggestUrl) {
                return;
            }
            this.suggestLoading = true;
            this.suggestOpen = true;
            const url = suggestUrl + '?' + this.buildSuggestParams().toString();
            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then((r) => r.ok ? r.json() : Promise.reject())
                .then((data) => {
                    this.suggestions = {
                        products: [],
                        categories: [],
                        manufacturers: [],
                        articles: [],
                        popular: data.popular || [],
                    };
                    this.suggestOpen = (data.popular || []).length > 0;
                })
                .catch(() => {
                    this.suggestions = { products: [], categories: [], manufacturers: [], articles: [], popular: [] };
                    this.suggestOpen = false;
                })
                .finally(() => { this.suggestLoading = false; });
        },
        pickPopularSearch(query) {
            const el = document.getElementById('catalog-search-input');
            if (el) {
                el.value = query;
            }
            this.fetchSuggestions();
        },
        submitSearch() {
            this.closeSuggestions();
            this.applyCatalogFilters();
        },
        pickSuggestCategory(slug) {
            this.closeSuggestions();
            this.loadCategory(slug);
        },
        pickSuggestManufacturer(id) {
            this.closeSuggestions();
            const form = document.getElementById('catalog-filters-form');
            if (!form) {
                return;
            }
            form.querySelectorAll('input[name="manufacturer_ids[]"]').forEach((el) => { el.checked = false; });
            const checkbox = form.querySelector('input[name="manufacturer_ids[]"][value="' + id + '"]');
            if (checkbox) {
                checkbox.checked = true;
            }
            this.applyCatalogFilters();
        },
    };
}

function catalogFiltersMixin() {
    return {
        catalogFiltersOpen: false,
    };
}

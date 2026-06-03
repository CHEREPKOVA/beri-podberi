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
        loading: false,
        buildCatalogParams(includeFilters) {
            const params = new URLSearchParams();
            if (this.selectedCategorySlug) {
                params.set('category', this.selectedCategorySlug);
            }
            const searchEl = document.getElementById('catalog-search-input');
            if (searchEl && searchEl.value.trim() !== '') {
                params.set('search', searchEl.value.trim());
            }
            if (includeFilters) {
                const form = document.getElementById('catalog-filters-form');
                if (form) {
                    new FormData(form).forEach((value, key) => {
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
            const params = new URLSearchParams();
            if (slug) {
                params.set('category', slug);
            }
            const searchEl = document.getElementById('catalog-search-input');
            if (searchEl && searchEl.value.trim() !== '') {
                params.set('search', searchEl.value.trim());
            }
            this.fetchCatalogProducts(params);
        },
        applyCatalogFilters() {
            this.fetchCatalogProducts(this.buildCatalogParams(true));
        },
        resetCatalogFilters() {
            const form = document.getElementById('catalog-filters-form');
            if (form) {
                form.reset();
            }
            const searchEl = document.getElementById('catalog-search-input');
            if (searchEl) {
                searchEl.value = '';
            }
            this.loadCategory(this.selectedCategorySlug);
        },
    };
}

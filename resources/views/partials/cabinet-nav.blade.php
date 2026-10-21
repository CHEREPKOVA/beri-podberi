<script>
(function () {
    const root = document.querySelector('[data-cabinet-root]');
    if (!root) return;

    let navToken = 0;
    let pageListeners = [];

    function isModifiedClick(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
    }

    function shouldIgnoreLink(anchor) {
        if (!anchor || anchor.dataset.noCabinetNav !== undefined) return true;
        if (anchor.hasAttribute('download')) return true;
        const target = (anchor.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') return true;
        const href = anchor.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
            return true;
        }
        let url;
        try {
            url = new URL(anchor.href, window.location.href);
        } catch (e) {
            return true;
        }
        if (url.origin !== window.location.origin) return true;
        if (url.pathname === window.location.pathname && url.search === window.location.search) return true;
        return !anchor.closest('[data-cabinet-sidebar], [data-cabinet-header], [data-cabinet-main]');
    }

    function progress(show) {
        const bar = document.querySelector('[data-cabinet-progress]');
        const main = document.querySelector('[data-cabinet-main]');
        if (bar) bar.hidden = !show;
        if (main) {
            main.classList.toggle('opacity-60', show);
            main.setAttribute('aria-busy', show ? 'true' : 'false');
        }
    }

    function destroyRegion(node) {
        if (node && window.Alpine && typeof Alpine.destroyTree === 'function') {
            try { Alpine.destroyTree(node); } catch (e) { /* узел уже снят */ }
        }
    }

    function initRegion(node) {
        if (node && window.Alpine && typeof Alpine.initTree === 'function') {
            Alpine.initTree(node);
        }
    }

    /**
     * Alpine держит MutationObserver и сам вызывает initTree на новых узлах.
     * Без паузы swap + наш initRegion дают двойную инициализацию (x-for дублирует блоки).
     */
    function withoutAlpineMutations(fn) {
        if (window.Alpine && typeof Alpine.mutateDom === 'function') {
            return Alpine.mutateDom(fn);
        }
        fn();
    }

    function clearPageListeners() {
        pageListeners.forEach(function (item) {
            const target = item[0] === 'window' ? window : document;
            target.removeEventListener(item[1], item[2], item[3]);
        });
        pageListeners = [];
    }

    function nodesBetween(doc, startMarker, endMarker) {
        const comments = [];
        const walker = doc.createTreeWalker(doc, NodeFilter.SHOW_COMMENT);
        let current;
        while ((current = walker.nextNode())) {
            const text = (current.textContent || '').trim();
            if (text === startMarker) comments[0] = current;
            if (text === endMarker) comments[1] = current;
        }
        if (!comments[0] || !comments[1]) return [];
        const nodes = [];
        let cursor = comments[0].nextSibling;
        while (cursor && cursor !== comments[1]) {
            nodes.push(cursor);
            cursor = cursor.nextSibling;
        }
        return nodes;
    }

    function replaceStyles(doc) {
        document.querySelectorAll('[data-cabinet-style]').forEach(function (node) { node.remove(); });
        nodesBetween(doc, 'cabinet-styles:start', 'cabinet-styles:end').forEach(function (node) {
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            const copy = document.importNode(node, true);
            copy.setAttribute('data-cabinet-style', '1');
            document.head.appendChild(copy);
        });
    }

    /**
     * Play CDN (cdn.tailwindcss.com) иногда не успевает/не сканирует классы
     * во вложенном HTML после replaceWith — страница остаётся без lg:/sm: утилит.
     * Полный rebuild через правку style[type=text/tailwindcss] пересканирует весь DOM.
     */
    function refreshTailwind() {
        const style = document.querySelector('style[type="text/tailwindcss"]');
        if (!style) return;
        const marker = '/*cabinet-nav-refresh*/';
        let css = style.textContent || '';
        if (css.indexOf(marker) !== -1) {
            css = css.split(marker).join('');
        } else {
            css = css + '\n' + marker;
        }
        style.textContent = css;
    }

    function runInlineScript(code) {
        const recorded = [];
        const originalDocumentAdd = document.addEventListener.bind(document);
        const originalWindowAdd = window.addEventListener.bind(window);

        function wrap(original, targetName) {
            return function (type, listener, options) {
                if ((type === 'DOMContentLoaded' || type === 'alpine:init') && typeof listener === 'function') {
                    try { listener(new Event(type)); } catch (err) { console.error(err); }
                    return;
                }
                recorded.push([targetName, type, listener, options]);
                return original(type, listener, options);
            };
        }

        document.addEventListener = wrap(originalDocumentAdd, 'document');
        window.addEventListener = wrap(originalWindowAdd, 'window');
        try {
            const script = document.createElement('script');
            script.setAttribute('data-cabinet-script', '1');
            script.textContent = code;
            document.body.appendChild(script);
        } finally {
            document.addEventListener = originalDocumentAdd;
            window.addEventListener = originalWindowAdd;
        }
        pageListeners = pageListeners.concat(recorded);
    }

    function runExternalScript(node) {
        const src = node.getAttribute('src');
        if (!src) return Promise.resolve();
        if (document.querySelector('script[src="' + src + '"]')) return Promise.resolve();
        return new Promise(function (resolve) {
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            script.setAttribute('data-cabinet-script', '1');
            ['crossorigin', 'integrity', 'referrerpolicy'].forEach(function (name) {
                const value = node.getAttribute(name);
                if (value) script.setAttribute(name, value);
            });
            script.onload = function () { resolve(); };
            script.onerror = function () { resolve(); };
            document.body.appendChild(script);
        });
    }

    async function runPageScripts(doc) {
        clearPageListeners();
        document.querySelectorAll('script[data-cabinet-script]').forEach(function (node) {
            if (!node.src) node.remove();
        });
        const scripts = nodesBetween(doc, 'cabinet-scripts:start', 'cabinet-scripts:end')
            .filter(function (node) { return node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SCRIPT'; });
        for (const node of scripts) {
            if (node.getAttribute('src')) {
                await runExternalScript(node);
            } else {
                runInlineScript(node.textContent || '');
            }
        }
    }

    function swapRegion(selector, doc) {
        const current = document.querySelector(selector);
        const fresh = doc.querySelector(selector);
        if (!fresh) {
            if (current) {
                destroyRegion(current);
                withoutAlpineMutations(function () { current.remove(); });
            }
            return null;
        }
        const copy = document.importNode(fresh, true);
        withoutAlpineMutations(function () {
            if (current) {
                destroyRegion(current);
                current.replaceWith(copy);
            } else {
                document.body.appendChild(copy);
            }
        });
        return copy;
    }

    function syncCartCount() {
        const aside = document.querySelector('[data-cabinet-sidebar]');
        if (!aside || !window.Alpine || typeof Alpine.$data !== 'function') return;
        const raw = aside.getAttribute('data-cart-count');
        if (raw === null) return;
        try {
            const data = Alpine.$data(document.body);
            if (data && 'cartItemsCount' in data) data.cartItemsCount = Number(raw) || 0;
        } catch (e) { /* счётчик обновится при следующей полной загрузке */ }
    }

    function scrollToHash(url) {
        if (!url.hash) {
            window.scrollTo(0, 0);
            return;
        }
        const id = decodeURIComponent(url.hash.slice(1));
        const target = id ? document.getElementById(id) : null;
        if (target) target.scrollIntoView();
        else window.scrollTo(0, 0);
    }

    async function loadCabinet(url, options) {
        const historyMode = (options && options.history) || 'push';
        const token = ++navToken;
        const requested = new URL(url, window.location.href);
        const home = root.getAttribute('data-cabinet-home') || '/dashboard';
        progress(true);
        try {
            const response = await fetch(requested.href, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html',
                    'X-Cabinet-Nav': '1',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (token !== navToken) return false;
            const type = response.headers.get('content-type') || '';
            if (!response.ok || !type.includes('text/html')) {
                if (historyMode === 'none' && !(options && options.fallback)) {
                    return loadCabinet(home, { history: 'replace', fallback: true });
                }
                window.location.href = requested.href;
                return false;
            }
            const html = await response.text();
            if (token !== navToken) return false;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            if (!doc.querySelector('[data-cabinet-root]')) {
                if (historyMode === 'none' && !(options && options.fallback)) {
                    return loadCabinet(home, { history: 'replace', fallback: true });
                }
                window.location.href = response.url || requested.href;
                return false;
            }

            const finalUrl = new URL(response.url || requested.href, window.location.href);
            if (requested.hash) finalUrl.hash = requested.hash;

            replaceStyles(doc);
            const aside = swapRegion('[data-cabinet-sidebar]', doc);
            const header = swapRegion('[data-cabinet-header]', doc);
            const main = swapRegion('[data-cabinet-main]', doc);
            const modal = swapRegion('[data-cabinet-role-modal]', doc);
            refreshTailwind();
            await runPageScripts(doc);
            if (token !== navToken) return false;
            initRegion(aside);
            initRegion(header);
            initRegion(main);
            initRegion(modal);
            // Повторно после скриптов/Alpine — на случай поздней подмены классов в DOM.
            requestAnimationFrame(function () { refreshTailwind(); });
            syncCartCount();
            if (doc.title) document.title = doc.title;
            const same = finalUrl.pathname === window.location.pathname && finalUrl.search === window.location.search && finalUrl.hash === window.location.hash;
            if (historyMode === 'push' && !same) history.pushState({ cabinet: true }, '', finalUrl.href);
            else if (historyMode !== 'none') history.replaceState({ cabinet: true }, '', finalUrl.href);
            scrollToHash(finalUrl);
            return true;
        } catch (e) {
            if (token === navToken) window.location.href = requested.href;
            return false;
        } finally {
            if (token === navToken) progress(false);
        }
    }

    async function switchRole(form) {
        const error = form.querySelector('[data-role-switch-error]');
        const button = form.querySelector('button[type="submit"]');
        if (error) {
            error.textContent = '';
            error.classList.add('hidden');
        }
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Cabinet-From': window.location.href,
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            const payload = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                const message = payload?.errors
                    ? Object.values(payload.errors).flat()[0]
                    : (payload?.message || 'Не удалось сменить роль.');
                if (error) {
                    error.textContent = message;
                    error.classList.remove('hidden');
                }
                return;
            }
            document.getElementById('role-switch-modal')?.classList.add('hidden');
            await loadCabinet(payload.url || window.location.href, { history: 'replace' });
        } catch (e) {
            if (error) {
                error.textContent = 'Ошибка сети. Попробуйте ещё раз.';
                error.classList.remove('hidden');
            }
        } finally {
            if (button) button.disabled = false;
        }
    }

    document.addEventListener('click', function (event) {
        if (isModifiedClick(event)) return;
        const anchor = event.target.closest ? event.target.closest('a[href]') : null;
        if (shouldIgnoreLink(anchor)) return;
        if (event.defaultPrevented) return;
        event.preventDefault();
        loadCabinet(anchor.href);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.dataset.roleSwitch !== undefined) {
            event.preventDefault();
            switchRole(form);
            return;
        }
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
        if (event.defaultPrevented || form.dataset.noCabinetNav !== undefined) return;
        if (!form.closest('[data-cabinet-main], [data-cabinet-sidebar], [data-cabinet-header]')) return;
        event.preventDefault();
        const action = form.getAttribute('action') || window.location.href;
        const url = new URL(action, window.location.href);
        url.search = '';
        const fields = new FormData(form);
        fields.forEach(function (value, key) {
            if (typeof value === 'string') url.searchParams.append(key, value);
        });
        loadCabinet(url.href);
    });

    window.addEventListener('popstate', function () {
        loadCabinet(window.location.href, { history: 'none' });
    });
})();
</script>

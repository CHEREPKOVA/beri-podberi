{{--
    Полноэкранный прелоадер на переходах между страницами.
    Стили инлайновые (без Tailwind CDN), чтобы оверлей был виден даже при медленной сети.
--}}
<style>
    #page-preloader {
        position: fixed;
        inset: 0;
        z-index: 2147483646;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        background: #ffffff;
        transition: opacity .18s ease, visibility .18s ease;
    }
    .dark #page-preloader {
        background: #111827;
    }
    #page-preloader.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    #page-preloader .page-preloader__spinner {
        width: 44px;
        height: 44px;
        border: 3px solid #fecaca;
        border-top-color: #c3242a;
        border-radius: 50%;
        animation: page-preloader-spin .7s linear infinite;
    }
    #page-preloader .page-preloader__text {
        margin: 0;
        font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: #6b7280;
    }
    @keyframes page-preloader-spin {
        to { transform: rotate(360deg); }
    }
    @media print {
        #page-preloader { display: none !important; }
    }
</style>
<div id="page-preloader" role="status" aria-live="polite" aria-label="Загрузка страницы">
    <div class="page-preloader__spinner" aria-hidden="true"></div>
    <p class="page-preloader__text">Загрузка…</p>
</div>
<script>
(function () {
    var el = document.getElementById('page-preloader');
    if (!el) return;
    var hideTimer = null;

    function showPreloader() {
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
        el.classList.remove('is-hidden');
    }

    function hidePreloader() {
        el.classList.add('is-hidden');
    }

    function hideWhenReady() {
        hideTimer = setTimeout(hidePreloader, 50);
    }

    if (document.readyState === 'complete') {
        hideWhenReady();
    } else {
        document.addEventListener('DOMContentLoaded', hideWhenReady);
        window.addEventListener('load', hidePreloader);
    }

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) hidePreloader();
    });

    function isModifiedClick(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
    }

    function shouldIgnoreLink(anchor) {
        if (!anchor || !anchor.getAttribute) return true;
        if (anchor.hasAttribute('download')) return true;
        if (anchor.dataset.noPreloader !== undefined) return true;
        var target = (anchor.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') return true;
        var href = anchor.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
            return true;
        }
        try {
            var url = new URL(anchor.href, window.location.href);
            if (url.origin !== window.location.origin) return true;
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
                return true;
            }
        } catch (e) {
            return true;
        }
        return false;
    }

    document.addEventListener('click', function (event) {
        if (isModifiedClick(event)) return;
        var anchor = event.target.closest ? event.target.closest('a[href]') : null;
        if (shouldIgnoreLink(anchor)) return;
        setTimeout(function () {
            if (!event.defaultPrevented) showPreloader();
        }, 0);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.dataset && form.dataset.noPreloader !== undefined) return;
        if (form.classList && form.classList.contains('js-add-to-cart')) return;
        var target = (form.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') return;
        setTimeout(function () {
            if (!event.defaultPrevented) showPreloader();
        }, 0);
    });
})();
</script>

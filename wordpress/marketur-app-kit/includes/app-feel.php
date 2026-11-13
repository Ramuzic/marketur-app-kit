<?php
/**
 * The app feel (App Kit 1.8.2), taken from the NexURLs app.
 *
 * Inside the iPhone and Android app only (the website is unchanged):
 *  - links to other websites open in an in-app browser sheet with a Done button,
 *  - moving between pages shows a thin loading bar in the brand color and a soft fade, never a white flash,
 *  - a light buzz on every tap (tiles, buttons, bottom bar, avatar, header icons) and a click buzz on switches and choices,
 *  - a success buzz when a comment, post or add-to-cart goes through, an error buzz when something fails,
 *  - no text selection or long-press previews on buttons, menus, tiles and pictures,
 *  - unread counts on the app icon and on a Chat tab (Marketur Chat and the community plugin),
 *  - pull to refresh switches itself off while a sheet, pop-up or menu is open, and Android gets its own,
 *  - a polite rating prompt once someone has used the app on a few different days (the phone decides if it shows),
 *  - "My PWA look": PWA PowerUp's bottom bar, launch animation and installed-app styles run inside the app.
 *
 * Calls into the app that an older app version does not have simply do nothing.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_App_Feel {

    public static function init() {
        add_action( 'wp_head', [ __CLASS__, 'head' ], 0 );
        add_action( 'wp_footer', [ __CLASS__, 'footer' ], 101 );
    }

    /** PWA PowerUp (and other PWA plugins) only turn on when the page says it is an installed app. In the app it is one. */
    public static function head() {
        if ( ! Marketur_App_Kit::in_app() ) return;
        $s = Marketur_App_Kit::get();
        $c = $s['colors'];
        echo '<style id="mak-feel-css">'
            . 'html.mak-app a,html.mak-app button,html.mak-app [role="button"],html.mak-app img,html.mak-app label,html.mak-app nav,html.mak-app .mka-tile,html.mak-app .mpwa-nav-link{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}'
            . 'html.mak-app .mka-card,html.mak-app .mka-post,html.mak-app .mka-topic,html.mak-app .mka-av,html.mak-app .mpwa-nav-link{transition:transform .12s ease}html.mak-app .mka-card:active,html.mak-app .mka-post:active,html.mak-app .mka-topic:active,html.mak-app .mka-av:active,html.mak-app .mpwa-nav-link:active{transform:scale(.96)}'
            . 'html.mak-app input,html.mak-app textarea,html.mak-app [contenteditable="true"]{-webkit-user-select:text;user-select:text;-webkit-touch-callout:default}'
            . '#mak-bar{position:fixed;left:0;top:0;height:3px;width:0;background:' . esc_attr( $c['active'] ) . ';z-index:2147483647;opacity:0;pointer-events:none;box-shadow:0 0 8px ' . esc_attr( $c['active'] ) . '}'
            . '#mak-bar.go{opacity:1;width:82%;transition:width 2.4s cubic-bezier(.1,.7,.2,1),opacity .15s}'
            . 'html.mak-app.mak-leaving body{opacity:.62;transition:opacity .2s ease}'
            . '#mak-ptr{position:fixed;left:50%;top:0;width:40px;height:40px;border-radius:50%;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.18);display:flex;align-items:center;justify-content:center;z-index:2147483646;opacity:0;transform:translate(-50%,-50px);pointer-events:none}'
            . '#mak-ptr.spin svg{animation:makspin .7s linear infinite}@keyframes makspin{to{transform:rotate(360deg)}}'
            . '</style>' . "\n";
        if ( ( $s['look'] ?? 'app' ) === 'pwa' ) {
            echo "<script id=\"mak-pwa-mode\">(function(){try{var mm=window.matchMedia&&window.matchMedia.bind(window);if(mm){window.matchMedia=function(q){if(/display-mode\\s*:\\s*(standalone|fullscreen)/.test(String(q))){var r=mm(q);return{matches:true,media:String(q),onchange:null,addListener:function(){},removeListener:function(){},addEventListener:function(){},removeEventListener:function(){},dispatchEvent:function(){return false}};}return mm(q);};}Object.defineProperty(window.navigator,'standalone',{configurable:true,get:function(){return true}});}catch(e){}document.documentElement.classList.add('mak-pwa-look','mpwa-ios-standalone');})();</script>\n";
        }
    }

    public static function footer() {
        if ( ! Marketur_App_Kit::in_app() ) return;
        $s = Marketur_App_Kit::get();
        if ( empty( $s['feel'] ) ) return;
        $cfg = [ 'bar' => $s['colors']['bar'], 'active' => $s['colors']['active'], 'home' => (string) wp_parse_url( Marketur_App_Kit::start_url(), PHP_URL_PATH ) ];
        ?>
<script id="mak-feel-js">
(function () {
    var CFG = <?php echo wp_json_encode( $cfg ); ?>;
    var C = window.Capacitor;
    if (!C || typeof C.nativePromise !== 'function') return;
    function call(p, m, o) { try { return C.nativePromise(p, m, o || {}); } catch (e) { return Promise.reject(e); } }
    var lastHap = 0;
    function hap(s) { var t = Date.now(); if (s === 'light' || s === 'selection') { if (t - lastHap < 120) return; lastHap = t; } call('NexApp', 'haptic', { style: s }).catch(function () {}); }

    /* A light tap buzz on everything you can press, the NexURLs way: home tiles, buttons, the bottom bar, the avatar and header icons, menus. */
    var TAP = 'a[href],button,[role="button"],[role="tab"],[role="menuitem"],[role="switch"],summary,label,[onclick],input[type="submit"],input[type="button"],.mka-tile,.mka-card,.mka-post,.mka-topic,.mka-av,.mpwa-nav-link,[class*="avatar"],[class*="icon-btn"],[class*="menu-toggle"],[class*="hamburger"]';
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest(TAP) : null;
        if (!el || el.disabled || el.getAttribute('aria-disabled') === 'true') return;
        if (el.tagName === 'LABEL' && el.control && /^(text|email|password|search|tel|url|number)$/.test(el.control.type || '')) return;
        hap('light');
    }, true);
    document.addEventListener('change', function (e) {
        var t = e.target; if (!t || !t.tagName) return;
        if (t.tagName === 'SELECT' || t.type === 'checkbox' || t.type === 'radio' || t.type === 'range') hap('selection');
    }, true);
    function bare(h) { h = String(h || '').toLowerCase(); return h.indexOf('www.') === 0 ? h.slice(4) : h; }
    var HOME = bare(location.host);
    function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }

    /* Other websites open in an in-app browser sheet. */
    function external(u) { return (u.protocol === 'https:' || u.protocol === 'http:') && bare(u.host) !== HOME && bare(u.host).slice(-HOME.length - 1) !== '.' + HOME; }
    function openSheet(href) { hap('light'); return call('Browser', 'open', { url: href, toolbarColor: CFG.bar, presentationStyle: 'fullscreen' }); }
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button > 0 || e.metaKey || e.ctrlKey) return;
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.hasAttribute('download')) return;
        var u; try { u = new URL(a.getAttribute('href'), location.href); } catch (x) { return; }
        if (!external(u)) {
            /* Our own pages set to open in a new tab would leave the app. Open them right here. */
            if ((a.getAttribute('target') || '').toLowerCase() === '_blank' && (u.protocol === 'https:' || u.protocol === 'http:')) { e.preventDefault(); e.stopPropagation(); hap('light'); location.href = u.href; }
            return;
        }
        e.preventDefault(); e.stopPropagation();
        openSheet(u.href).catch(function () { location.href = u.href; });
    }, true);
    var wopen = window.open;
    window.open = function (url) {
        try {
            var u = new URL(String(url || ''), location.href);
            if (url && external(u)) { openSheet(u.href).catch(function () {}); return null; }
            if (url && (u.protocol === 'https:' || u.protocol === 'http:')) { location.href = u.href; return null; }
        } catch (x) {}
        return wopen.apply(window, arguments);
    };

    /* Moving between pages: a thin loading bar and a soft fade. */
    var bar = document.createElement('div'); bar.id = 'mak-bar';
    function leaving() {
        if (!bar.parentNode && document.body) document.body.appendChild(bar);
        bar.className = ''; void bar.offsetWidth; bar.className = 'go';
        document.documentElement.classList.add('mak-leaving');
        clearTimeout(leaving.t); leaving.t = setTimeout(arrived, 9000);
    }
    function arrived() { bar.className = ''; document.documentElement.classList.remove('mak-leaving'); }
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        setTimeout(function () {
            if (e.defaultPrevented || a.target === '_blank' || a.hasAttribute('download')) return;
            var u; try { u = new URL(a.getAttribute('href'), location.href); } catch (x) { return; }
            if (external(u) || (u.protocol !== 'https:' && u.protocol !== 'http:')) return;
            if (u.pathname === location.pathname && u.search === location.search && u.hash) return;
            if (u.href === location.href) return;
            leaving();
        }, 0);
    });
    document.addEventListener('submit', function (e) { if (!e.defaultPrevented) setTimeout(function () { if (!e.defaultPrevented) leaving(); }, 0); });
    window.addEventListener('pageshow', arrived);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) arrived(); });

    /* Buzzes: success when something people post goes up, error when it fails. */
    var SUCCESS = ['/comments', '/qcf/v1/posts', '/cart/add-item', 'add_to_cart', '/wp/v2/posts'];
    var QUIET = ['/view', '/visit', '/seen', 'heartbeat', '/app-config', 'admin-ajax.php?action=heartbeat', '/mak/v1/tiles'];
    if (window.fetch) {
        var of = window.fetch;
        window.fetch = function (input, init) {
            var p = of.apply(this, arguments);
            try {
                var m = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
                var url = String((input && input.url) || input || '');
                if (m !== 'GET' && !QUIET.some(function (q) { return url.indexOf(q) > -1; })) {
                    var hit = SUCCESS.some(function (s) { return url.indexOf(s) > -1; });
                    p.then(function (r) { if (r.status >= 400 && r.status !== 401) hap('error'); else if (r.ok && hit) hap('success'); }, function () {});
                }
            } catch (x) {}
            return p;
        };
    }
    if (window.jQuery) { try { jQuery(document.body).on('added_to_cart', function () { hap('success'); }); } catch (x) {} }

    /* Unread counts: the app icon, and the Chat tab when the app has one. */
    var lastBadge = '';
    function badge() {
        var n = 0, b = document.getElementById('qcf-notif-btn');
        if (b) n = parseInt(String(b.textContent || '').replace(/[^0-9]/g, ''), 10) || 0;
        var chat = parseInt(window.mcUnread, 10) || 0;
        if (!document.body || !document.body.classList.contains('logged-in')) { n = 0; chat = 0; }
        var key = n + ':' + chat;
        if (key === lastBadge) return;
        lastBadge = key;
        call('NexApp', 'setBadge', { count: n + chat }).catch(function () {});
        call('NexApp', 'setTabBadge', { action: 'chat', count: chat }).catch(function () {});
    }
    document.addEventListener('mc:unread', function () { later(); });

    /* Pull to refresh stays out of the way while a sheet, pop-up or full screen menu is open. */
    var lastPull = null;
    function blocked() {
        var h = document.documentElement, b = document.body;
        if (!b) return false;
        if (getComputedStyle(b).overflow === 'hidden' || getComputedStyle(h).overflow === 'hidden') return true;
        return !!document.querySelector('[aria-modal="true"], dialog[open], .mka-sheet-bg, #mpwa-more-overlay.mpwa-show, #bmc-mobile-app.open, .qcf-modal.open, .qcf-composer-ov.open, .pswp--open, .mfp-ready, .elementor-popup-modal');
    }
    function pull() {
        var off = blocked();
        if (off === lastPull) return;
        lastPull = off;
        call('NexApp', 'setRefresh', { on: !off }).catch(function () {});
    }

    /* Android pull to refresh (the iPhone app has its own). */
    if (/Android/.test(navigator.userAgent)) {
        var ptr = document.createElement('div'), y0 = -1, armed = false, dist = 0;
        ptr.id = 'mak-ptr';
        ptr.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5" fill="none" stroke="' + CFG.active + '" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var atTop = function (el) {
            if ((window.pageYOffset || document.documentElement.scrollTop || 0) > 0) return false;
            for (; el && el !== document.body && el !== document.documentElement; el = el.parentElement) if (el.scrollTop > 0) return false;
            return true;
        };
        var show = function (d) { if (!ptr.parentNode && document.body) document.body.appendChild(ptr); var y = Math.min(d, 90); ptr.style.transform = 'translate(-50%,' + (y - 50) + 'px) rotate(' + (d * 3) + 'deg)'; ptr.style.opacity = String(Math.min(1, d / 60)); };
        var reset = function () { y0 = -1; armed = false; dist = 0; ptr.style.opacity = '0'; ptr.style.transform = 'translate(-50%,-50px)'; };
        document.addEventListener('touchstart', function (e) { if (blocked() || e.touches.length !== 1 || !atTop(e.target)) { y0 = -1; return; } y0 = e.touches[0].clientY; armed = false; dist = 0; }, { passive: true });
        document.addEventListener('touchmove', function (e) { if (y0 < 0) return; dist = (e.touches[0].clientY - y0) * 0.5; if (dist <= 0) { reset(); return; } show(dist); if (dist > 64 && !armed) { armed = true; hap('selection'); } else if (dist <= 64 && armed) armed = false; }, { passive: true });
        document.addEventListener('touchend', function () { if (y0 < 0) return; if (armed) { hap('medium'); ptr.classList.add('spin'); show(70); location.reload(); return; } reset(); }, { passive: true });
    }

    var queued = false;
    function scan() { queued = false; badge(); pull(); }
    function later() { if (!queued) { queued = true; setTimeout(scan, 300); } }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
    if (window.MutationObserver) new MutationObserver(later).observe(document.documentElement, { attributes: true, childList: true, subtree: true, attributeFilter: ['class', 'style', 'open', 'aria-modal'] });

    /* A rating prompt once someone clearly likes the app: opened on 4 different days over at least a week, never more than once in four months. */
    var day = new Date().toISOString().slice(0, 10);
    if (!ls('mak_first')) ls('mak_first', String(Date.now()));
    if (ls('mak_day') !== day) { ls('mak_day', day); ls('mak_days', String((parseInt(ls('mak_days') || '0', 10) || 0) + 1)); }
    if (CFG.home && location.pathname === CFG.home) {
        setTimeout(function () {
            var days = parseInt(ls('mak_days') || '0', 10) || 0, first = parseInt(ls('mak_first') || '0', 10) || Date.now(), asked = parseInt(ls('mak_rate_at') || '0', 10) || 0;
            if (days >= 4 && Date.now() - first > 7 * 864e5 && Date.now() - asked > 120 * 864e5) { ls('mak_rate_at', String(Date.now())); call('NexApp', 'requestReview', {}).catch(function () {}); }
        }, 2500);
    }
})();
</script>
        <?php
    }
}

MAK_App_Feel::init();

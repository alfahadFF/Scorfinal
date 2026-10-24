/* SCOR language preference: auto-detect on the EN homepage only (SEO-safe, JS-only). */
(function () {
    var KEY = 'scor-lang';

    // Remember a manual choice made in the language switcher.
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('#localization a') : null;
        if (!a) return;
        var h = a.getAttribute('href') || '';
        var m = h.match(/^\/(ar)(\/|$)/);
        var lang = null;
        if (m) lang = m[1];
        else if (h === '/' || h.indexOf('/en') === 0 || h === 'index.html') lang = 'en';
        if (lang) { try { localStorage.setItem(KEY, lang); } catch (err) {} }
    });

    // Auto-detect: English homepage only.
    var p = location.pathname;
    if (!(p === '/' || p === '/index.html')) return;

    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (err) {}
    if (saved) {
        if (saved === 'ar' || saved === 'de' || saved === 'tr') location.replace('/' + saved + '/');
        return;
    }
    var langs = navigator.languages || [navigator.language || 'en'];
    for (var i = 0; i < langs.length; i++) {
        var l = (langs[i] || '').toLowerCase().split('-')[0];
        if (l === 'ar' || l === 'de' || l === 'tr') {
            try { localStorage.setItem(KEY, l); } catch (err) {}
            location.replace('/' + l + '/');
            return;
        }
    }
    try { localStorage.setItem(KEY, 'en'); } catch (err) {}
})();

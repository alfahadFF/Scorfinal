/* SCOR language globe button (standalone launcher for #localization) */
(function () {
  function init() {
    if (document.querySelector('.scor-lang-btn')) return;
    var lang = (document.documentElement.getAttribute('lang') || 'en').toLowerCase();
    var current = lang.indexOf('ar') === 0 ? 'عربي' : 'EN';
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'scor-lang-btn';
    btn.setAttribute('aria-label', 'Language / اللغة');
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.7 2.6 4 5.6 4 9s-1.3 6.4-4 9c-2.7-2.6-4-5.6-4-9s1.3-6.4 4-9z"/></svg><span>' + current + '</span>';
    btn.addEventListener('click', function () {
      var loc = document.querySelector('#localization');
      if (loc) { loc.style.display = 'block'; }
    });
    (document.querySelector('header') || document.body).appendChild(btn);
    var loc = document.querySelector('#localization');
    if (loc) {
      loc.addEventListener('click', function (ev) {
        if (ev.target === loc || (ev.target.closest && !ev.target.closest('.container'))) { loc.style.display = 'none'; }
      });
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();

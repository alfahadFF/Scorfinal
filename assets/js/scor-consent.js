/* SCOR temporary cookie consent (self-hosted).
   Stores the visitor choice locally. When SCOR analytics/marketing tools
   are added later, they must only load after an "accepted" choice:
   window.SCORDConsent.status() === 'accepted'                        */
(function () {
  var KEY = 'scor-consent-v1';
  function read() {
    try { var v = localStorage.getItem(KEY); return v ? JSON.parse(v) : null; }
    catch (e) { return null; }
  }
  function save(choice) {
    try { localStorage.setItem(KEY, JSON.stringify({ choice: choice, ts: Date.now() })); } catch (e) {}
    document.dispatchEvent(new CustomEvent('scor:consent', { detail: { choice: choice } }));
  }
  window.SCORDConsent = {
    status: function () { var r = read(); return r ? r.choice : null; },
    reset: function () { try { localStorage.removeItem(KEY); } catch (e) {} show(); }
  };
  var banner = null;
  var AR = (document.documentElement.lang || '').toLowerCase().indexOf('ar') === 0;
  var T = AR ? {
      label: 'موافقة ملفات تعريف الارتباط',
      head: 'نحن نقدّر خصوصيتك.',
      body: 'نستخدم ملفات تعريف الارتباط لتحسين تجربة تصفحك وتحليل حركة الموقع. يمكنك قبول أو رفض ملفات تعريف الارتباط غير الأساسية. اقرأ ',
      policy: 'سياسة الخصوصية',
      reject: 'رفض', accept: 'قبول الكل', settings: 'إعدادات ملفات تعريف الارتباط'
    } : {
      label: 'Cookie consent',
      head: 'We value your privacy.',
      body: 'We use cookies to improve your browsing experience and to analyse site traffic. You can accept or reject non-essential cookies. Read our ',
      policy: 'Privacy Policy',
      reject: 'Reject', accept: 'Accept All', settings: 'Cookie Settings'
    };
  function hide() { if (banner) { banner.classList.remove('show'); setTimeout(function () { banner.remove(); banner = null; }, 500); } }
  function show() {
    if (banner) return;
    var privacy = (document.currentScript && document.currentScript.dataset.privacy) || 'en/services/protection-of-personal-data.html';
    banner = document.createElement('div');
    banner.className = 'scor-consent';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', T.label);
    if (AR) { banner.setAttribute('dir', 'rtl'); }
    banner.innerHTML =
      '<div class="sc-inner">' +
        '<div class="sc-text"><strong>' + T.head + '</strong> ' +
        T.body + '<a href="' + privacy + '">' + T.policy + '</a>.</div>' +
        '<div class="sc-actions">' +
          '<button type="button" class="sc-reject">' + T.reject + '</button>' +
          '<button type="button" class="sc-accept">' + T.accept + '</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(banner);
    requestAnimationFrame(function () { banner.classList.add('show'); });
    banner.querySelector('.sc-accept').addEventListener('click', function () { save('accepted'); hide(); });
    banner.querySelector('.sc-reject').addEventListener('click', function () { save('rejected'); hide(); });
  }
  function addSettingsLink() {
    var copy = document.querySelector('.scor-footer .sf-copy');
    if (copy && !copy.querySelector('.scor-consent-settings')) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'scor-consent-settings'; b.textContent = T.settings;
      b.addEventListener('click', function () { window.SCORDConsent.reset(); });
      copy.appendChild(b);
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
    addSettingsLink();
    if (!read()) show();
  });
})();

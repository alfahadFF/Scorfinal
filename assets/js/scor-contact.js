/* SCOR contact form -> /api/contact.php (one.com PHP mail handler).
   Sends to the official inbox info@scor-sy.de. Replaces the legacy
   ajaxForm flow that posted to the dead /ajax/forms/1 endpoint.
   Capture-phase delegation on document: runs before any template handler. */
(function () {
  var MSG = {
    en: { sending: 'Sending...', ok: 'Thank you! Your message has been sent successfully. We will contact you shortly.', err: 'Sorry, sending failed. Please write to us directly at info@scor-sy.de.' },
    de: { sending: 'Senden...', ok: 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet. Wir melden uns in Kürze bei Ihnen.', err: 'Das Senden ist leider fehlgeschlagen. Bitte schreiben Sie uns direkt an info@scor-sy.de.' },
    tr: { sending: 'Gönderiliyor...', ok: 'Teşekkürler! Mesajınız başarıyla gönderildi. Kısa süre içinde size ulaşacağız.', err: 'Gönderim başarısız oldu. Lütfen doğrudan info@scor-sy.de adresine yazın.' },
    ar: { sending: 'جارٍ الإرسال...', ok: 'شكرًا لك! تم إرسال رسالتك بنجاح. سنتصل بك قريبًا.', err: 'عذرًا، فشل الإرسال. يرجى مراسلتنا مباشرة على info@scor-sy.de.' }
  };

  function send(form) {
    if (form.dataset.scorSending === '1') return;
    if (form.checkValidity && !form.checkValidity()) { form.reportValidity(); return; }
    form.dataset.scorSending = '1';
    var seg = (location.pathname.split('/')[1] || 'en').toLowerCase();
    var msg = MSG[['ar', 'de', 'tr'].indexOf(seg) >= 0 ? seg : 'en'];
    var btn = form.querySelector('button[type="submit"]');
    var oldText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = msg.sending; }
    var body = new URLSearchParams(new FormData(form)).toString();
    fetch('/api/contact.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    }).then(function (r) {
      return r.json().then(function (d) { return r.ok && d && d.ok === true; }, function () { return false; });
    }).then(function (sent) {
      if (!sent) throw new Error('bad');
      form.innerHTML = '<div style="padding:28px 18px;text-align:center;font-size:18px;font-weight:700;color:#151515">' + msg.ok + '</div>';
    }).catch(function () {
      form.dataset.scorSending = '0';
      if (btn) { btn.disabled = false; btn.textContent = oldText; }
      var box = form.querySelector('.scor-form-error');
      if (!box) {
        box = document.createElement('div');
        box.className = 'scor-form-error';
        box.style.cssText = 'margin-top:12px;padding:12px 14px;background:#fdecec;color:#8a1f1f;border:1px solid #e5b4b4;font-weight:600';
        form.appendChild(box);
      }
      box.textContent = msg.err;
    });
  }

  // The template's ajaxForm hijacks the submit-button click and sends its own
  // XHR without a submit event, so intercept the click in capture phase.
  document.addEventListener('click', function (ev) {
    var el = ev.target;
    if (!el || !el.closest) return;
    var btn = el.closest('#contactForm button[type="submit"], #contactForm input[type="submit"]');
    if (!btn) return;
    ev.preventDefault();
    ev.stopPropagation();
    send(btn.form || document.getElementById('contactForm'));
  }, true);

  // Enter-key submissions (no click): intercept the submit event itself.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || form.id !== 'contactForm') return;
    ev.preventDefault();
    ev.stopPropagation();
    send(form);
  }, true);
})();

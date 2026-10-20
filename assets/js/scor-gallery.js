/* SCOR product gallery: slow auto slider (Alibaba-style) + lightbox for extra views. */
(function () {
    function initSlider(root) {
        var slides = root.querySelectorAll('.scor-slider-stage img.slide');
        var thumbs = root.querySelectorAll('.scor-slider-thumbs button');
        var counter = root.querySelector('.counter');
        if (!slides.length) return;
        var i = 0, delay = parseInt(root.getAttribute('data-autoplay') || '6000', 10), timer = null;

        function go(n) {
            i = (n + slides.length) % slides.length;
            slides.forEach(function (s, k) { s.classList.toggle('active', k === i); });
            thumbs.forEach(function (t, k) { t.classList.toggle('active', k === i); });
            if (counter) counter.textContent = (i + 1) + ' / ' + slides.length;
        }
        function play() { stop(); if (slides.length > 1) timer = setInterval(function () { go(i + 1); }, delay); }
        function stop() { if (timer) clearInterval(timer); }

        var prev = root.querySelector('.nav.prev'), next = root.querySelector('.nav.next');
        if (prev) prev.addEventListener('click', function () { go(i - 1); play(); });
        if (next) next.addEventListener('click', function () { go(i + 1); play(); });
        thumbs.forEach(function (t, k) { t.addEventListener('click', function () { go(k); play(); }); });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', play);

        var x0 = null;
        root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
        root.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1));
            x0 = null; play();
        }, { passive: true });

        go(0); play();
    }

    function initLightbox() {
        var box = document.querySelector('.scor-lightbox');
        if (!box) return;
        var img = box.querySelector('img');
        document.querySelectorAll('.scor-more-views img').forEach(function (el) {
            el.addEventListener('click', function () { img.src = el.src; box.classList.add('open'); });
        });
        box.addEventListener('click', function () { box.classList.remove('open'); img.src = ''; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') box.classList.remove('open'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.scor-slider').forEach(initSlider);
        initLightbox();
    });
})();

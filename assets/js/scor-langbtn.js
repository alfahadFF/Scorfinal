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

/* Add the MS category to existing header menus. Other menu items stay intact. */
(function(){
  function addMortarCategory(){
    var lang=(document.documentElement.lang||'en').toLowerCase().split('-')[0];
    var labels={ar:'ماكينات رش الملاط واللياسة',en:'Mortar Spraying Machines',de:'Mörtelspritzmaschinen',tr:'Harç Püskürtme Makineleri'};
    if(!labels[lang]) lang='en';
    var href='/'+lang+'/products/mortar-spraying-machines.html';
    var anchor=document.querySelector('#mobileMenu a[href$="trailer-concrete-pumps.html"]');
    if(anchor){
      var list=anchor.closest('ul');
      if(list && !list.querySelector('a[href="'+href+'"]')){
        var item=document.createElement('li'),link=document.createElement('a');
        link.href=href;link.textContent=labels[lang];item.appendChild(link);list.appendChild(item);
      }
    }
    var menu=document.querySelector('#subHeader .productGroups');
    if(menu && !menu.querySelector('a[href="'+href+'"]')){
      var item=document.createElement('li');item.className='columns small-3';
      var wrap=document.createElement('div');wrap.className='transition';
      var title=document.createElement('div');title.className='title';
      var link=document.createElement('a');link.href=href;link.textContent=labels[lang];title.appendChild(link);
      var sub=document.createElement('div');sub.className='sub';
      var image=document.createElement('div');image.className='image';
      var imgLink=document.createElement('a');imgLink.href=href;
      var img=document.createElement('img');img.src='/assets/img/scor-products/ms50/1.jpg';img.alt=labels[lang]+' — SCOR MS 50';
      img.width=1024;img.height=765;img.decoding='async';img.loading='eager';imgLink.appendChild(img);image.appendChild(imgLink);
      wrap.appendChild(title);wrap.appendChild(sub);wrap.appendChild(image);item.appendChild(wrap);menu.appendChild(item);
    }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',addMortarCategory);
  else addMortarCategory();
})();

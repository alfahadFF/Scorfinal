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
    if(menu){
      // Compare resolved category paths, not only literal href attributes.
      // Count list items (not anchors), since one card may link its title and image.
      var isMortarLink=function(a){
        try {
          var u=new URL(a.getAttribute('href'),document.baseURI);
          var isInternal=u.origin===window.location.origin || u.hostname==='scor-sy.de' || u.hostname==='www.scor-sy.de';
          return isInternal && /\/products\/mortar-spraying-machines(?:\.html)?\/?$/i.test(u.pathname);
        } catch(e){return false;}
      };
      var dedupe=function(){
        var items=Array.prototype.filter.call(menu.children,function(item){
          return item.tagName==='LI' && Array.prototype.some.call(item.querySelectorAll('a[href]'),isMortarLink);
        });
        var keep=items.filter(function(item){
          return Array.prototype.some.call(item.querySelectorAll('a[href]'),function(a){
            return isMortarLink(a) && new URL(a.getAttribute('href'),document.baseURI).pathname===href;
          });
        })[0] || items[0];
        items.forEach(function(item){
          if(item===keep) return;
          var links=item.querySelectorAll('a[href]');
          if(links.length && Array.prototype.every.call(links,isMortarLink) && !item.querySelector('button,input,select,textarea')) item.remove();
        });
        return keep;
      };
      var keep=dedupe();
      if(!keep){
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
      // Also guard against a duplicate added after the initial page load.
      if(window.MutationObserver && !menu.__scorMortarGuard){
        menu.__scorMortarGuard=new MutationObserver(dedupe);
        menu.__scorMortarGuard.observe(menu,{childList:true,subtree:true,attributes:true,attributeFilter:['href']});
      }
    }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',addMortarCategory);
  else addMortarCategory();
})();

/* SCOR desktop navigation: hover, click and keyboard without locking page scroll. */
(function () {
  'use strict';
  var desktop = window.matchMedia('(min-width: 1025px)');

  function init() {
    var header = document.getElementById('subHeader');
    if (!header || header.dataset.scorDesktopNav === 'ready') return;
    var allItems = Array.prototype.slice.call(header.querySelectorAll('.headerNav .main-nav li.link'));
    var items = allItems.filter(function (item) {
      return item.querySelector(':scope > a.scrs') && item.querySelector(':scope > .dropDown');
    });
    if (!items.length) return;
    header.dataset.scorDesktopNav = 'ready';

    // The original theme binds hover to pageLock(true). Desktop menus must not
    // disable scrolling or the browser's keyboard handlers while merely hovered.
    if (window.jQuery) window.jQuery(allItems).off('mouseenter mouseleave');

    var globe = header.querySelector('.scor-lang-btn span');
    var globeMobileLabel = globe ? globe.textContent : '';
    var labels = { ar: 'عربي', en: 'EN', de: 'DE', tr: 'TR' };
    var language = (document.documentElement.lang || 'en').toLowerCase().split('-')[0];
    var controlFor = function (item) { return item.querySelector(':scope > a.scrs'); };
    var panelFor = function (item) { return item.querySelector(':scope > .dropDown'); };

    function positionPanel(item) {
      var panel = panelFor(item), margin = 12;
      var overflow = function (rect) {
        return Math.max(0, margin - rect.left) + Math.max(0, rect.right - (window.innerWidth - margin));
      };
      panel.style.removeProperty('--scor-panel-shift');
      item.classList.remove('scor-nav-end');
      var startRect = panel.getBoundingClientRect();
      item.classList.add('scor-nav-end');
      var endRect = panel.getBoundingClientRect();
      if (overflow(startRect) <= overflow(endRect)) item.classList.remove('scor-nav-end');
      var rect = panel.getBoundingClientRect();
      var shift = Math.max(0, margin - rect.left) - Math.max(0, rect.right - (window.innerWidth - margin));
      if (shift) panel.style.setProperty('--scor-panel-shift', shift + 'px');
    }
    function setExpanded(item, expanded) {
      controlFor(item).setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
    function closeItem(item, suppress) {
      item.classList.remove('scor-nav-open');
      if (suppress) item.classList.add('scor-nav-suppressed');
      else item.classList.remove('scor-nav-suppressed');
      setExpanded(item, false);
    }
    function closeOthers(except, forceSuppress) {
      items.forEach(function (item) {
        if (item === except) return;
        var stillEngaged = item.matches(':hover') || item.contains(document.activeElement);
        closeItem(item, forceSuppress || stillEngaged);
      });
    }
    function begin(item) {
      closeOthers(item, false);
      item.classList.remove('scor-nav-suppressed');
      positionPanel(item);
      setExpanded(item, true);
    }
    function toggle(item) {
      var isPinned = item.classList.contains('scor-nav-open');
      if (isPinned) closeItem(item, true);
      else {
        begin(item);
        item.classList.add('scor-nav-open');
      }
    }
    function updateViewport() {
      if (globe) globe.textContent = desktop.matches ? (labels[language] || 'EN') : globeMobileLabel;
      if (!desktop.matches) {
        items.forEach(function (item) { closeItem(item, false); item.classList.remove('scor-nav-end'); panelFor(item).style.removeProperty('--scor-panel-shift'); });
      } else {
        items.forEach(function (item) {
          if (item.classList.contains('scor-nav-open') || item.matches(':hover') || item.contains(document.activeElement)) positionPanel(item);
        });
      }
    }
    items.forEach(function (item, index) {
      var control = controlFor(item);
      var panel = panelFor(item);
      if (!panel.id) panel.id = 'scor-desktop-menu-' + (index + 1);
      control.setAttribute('role', 'button');
      control.setAttribute('tabindex', '0');
      control.setAttribute('aria-haspopup', 'true');
      control.setAttribute('aria-controls', panel.id);
      control.setAttribute('aria-expanded', 'false');
      item.classList.remove('active');

      item.addEventListener('mouseenter', function () { if (desktop.matches) begin(item); });
      item.addEventListener('mouseleave', function () {
        if (!desktop.matches) return;
        if (!item.classList.contains('scor-nav-open') && !item.contains(document.activeElement)) {
          item.classList.remove('scor-nav-suppressed');
          setExpanded(item, false);
        }
      });
      item.addEventListener('focusin', function () {
        if (desktop.matches && !item.classList.contains('scor-nav-suppressed')) begin(item);
      });
      item.addEventListener('focusout', function () {
        if (!desktop.matches) return;
        window.setTimeout(function () {
          if (!item.contains(document.activeElement) && !item.classList.contains('scor-nav-open') && !item.matches(':hover')) {
            closeItem(item, false);
          }
        }, 0);
      });
      control.addEventListener('click', function (event) {
        if (!desktop.matches) return;
        event.preventDefault();
        toggle(item);
      });
      control.addEventListener('keydown', function (event) {
        if (!desktop.matches) return;
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          toggle(item);
        } else if (event.key === 'ArrowDown') {
          event.preventDefault();
          begin(item);
          item.classList.add('scor-nav-open');
          var first = panel.querySelector('a[href]');
          if (first) first.focus();
        }
      });
    });
    document.addEventListener('click', function (event) {
      if (!desktop.matches) return;
      var inside = items.some(function (item) { return item.contains(event.target); });
      if (!inside) closeOthers(null, false);
    });
    document.addEventListener('keydown', function (event) {
      if (!desktop.matches || event.key !== 'Escape') return;
      var current = items.find(function (item) { return item.contains(document.activeElement); });
      closeOthers(null, true);
      if (current) controlFor(current).focus();
    });
    window.addEventListener('scroll', function () { if (desktop.matches) closeOthers(null, true); }, { passive: true });
    window.addEventListener('resize', updateViewport);
    updateViewport();
  }

  // Use the native ready event: an unrelated legacy jQuery-ready error on some
  // pages can prevent later jQuery callbacks from running.
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

/* Give the existing mobile menu control keyboard and screen-reader semantics. */
(function () {
  function initMobileControl() {
    var menu=document.getElementById('mobileMenu');
    var trigger=menu && menu.querySelector('.openNav');
    var panel=menu && menu.querySelector('.row.dropDown');
    if(!trigger || !panel) return;
    if(!panel.id) panel.id='scor-mobile-navigation';
    var lang=(document.documentElement.lang || 'en').toLowerCase().split('-')[0];
    var labels={ar:'القائمة',en:'Menu',de:'Menü',tr:'Menü'};
    trigger.setAttribute('role','button');trigger.setAttribute('tabindex','0');
    trigger.setAttribute('aria-label',labels[lang] || labels.en);
    trigger.setAttribute('aria-controls',panel.id);
    function update(){trigger.setAttribute('aria-expanded',menu.classList.contains('open')?'true':'false');}
    update();
    trigger.addEventListener('click',function(){window.setTimeout(update,0);});
    trigger.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();trigger.click();}});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initMobileControl);
  else initMobileControl();
})();

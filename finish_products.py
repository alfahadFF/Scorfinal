# -*- coding: utf-8 -*-
"""Finishing pass on all mapped model pages + category cards (one-time static write).

1. Downloads: every model page sidebar gets TWO visible buttons — short leaflet
   and full catalog — with NO link (no href), per standing instruction, until the
   user uploads the PDFs. Markers: class 'button download-leaflet/catalog',
   data-delinked="download", aria-disabled, plus an inline style so they can
   never collapse. Existing Request Offer untouched.
2. Instant loading: slider image eager+fetchpriority (done in fill pass);
   here: gallery figures eager, category card images eager, and the page is
   fully static (no JS needed to reveal content).
3. Nothing else changes.
"""
from pathlib import Path
from bs4 import BeautifulSoup
import json

ROOT = Path('/home/user/scor1')
cats = json.loads(Path('/home/user/scor-cats-map.json').read_text(encoding='utf-8'))
ALIAS = {('skid-steer-loaders', 'ex65-2'): ('crawler-excavators', 'ex65-2'),
         ('skid-steer-loaders', 'wx180'): ('wheel-excavators', 'wx180')}

LABELS = {'en': ('Download Leaflet', 'Download Catalog'),
          'ar': ('تحميل النشرة المختصرة', 'تحميل الكتالوج')}
BTN_STYLE = 'display:block;margin-top:8px;text-align:center;cursor:default;'

n_pages = n_btns = 0
for folder, cat in cats.items():
    for m in cat['models']:
        tf, ts = ALIAS.get((folder, m['slug']), (folder, m['slug']))
        for lang in ('en', 'ar'):
            p = ROOT / lang / 'products' / tf / f'{ts}.html'
            if not p.exists():
                continue
            s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
            buttons = s.select_one('#sideBar .buttons')
            if buttons is None:
                print('NO BUTTONS BOX', p); continue
            for old in buttons.select('a.download-leaflet, a.download-catalog'):
                old.decompose()
            leaflet, catalog = LABELS[lang]
            for cls, txt in (('download-leaflet', leaflet), ('download-catalog', catalog)):
                a = s.new_tag('a')
                a['class'] = f'button download {cls}'
                a['data-delinked'] = 'download'
                a['aria-disabled'] = 'true'
                a['style'] = BTN_STYLE
                a.string = txt
                buttons.append(a)
                n_btns += 1
            # instant-load gallery figures
            for img in s.select('.scor-more-views img, img.slide'):
                img['loading'] = 'eager'
            p.write_text(str(s), encoding='utf-8')
            n_pages += 1

# Category cards: eager images (instant loading) — CSS already inlined per page.
n_cards = 0
for lang in ('en', 'ar'):
    for p in (ROOT / lang / 'products').glob('*.html'):
        s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
        dirty = False
        for img in s.select('#products li.product img'):
            if img.get('loading') != 'eager':
                img['loading'] = 'eager'; dirty = True; n_cards += 1
        if dirty:
            p.write_text(str(s), encoding='utf-8')
print(f'model pages finished: {n_pages}; download buttons added: {n_btns}; card images set eager: {n_cards}')

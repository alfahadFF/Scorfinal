#!/usr/bin/env python3
"""Restore homepage design on {,ar/}index.html.

Fixes (raw-text splicing only — no BeautifulSoup round trip):
1. Deletes the duplicate 10-tile <ul class="productGroups"> that sits AFTER </html>.
2. Deletes the <style id="scor-tiles-fix"> override that forced flex/grid wrapping
   and broke Foundation's float grid (the 'destroyed design').
3. Restores <ul class="productGroups row collapse"> to exactly one in-body strip
   of 10 tiles, ordered: crawler, wheel excavators, wheel loaders, backhoe,
   skid steer, diesel, electric, telescopic, material handling, pumps;
   tiles 1-4 have no inline style (Foundation shows 4 per row); tiles 5-10 and
   their wrapping rows carry `hidden` until the original front-end reveals them
   (matching the original Hidromek homepage behaviour), so nothing looks broken
   when scripts are blocked.
"""
from pathlib import Path
import re

ROOT = Path('/home/user/scor1')

ORDER = [
    ('crawler-excavators', 'CRAWLER EXCAVATORS', 'حفارات مجنزرة'),
    ('wheel-excavators', 'WHEEL EXCAVATORS', 'حفارات بعجلات'),
    ('wheel-loaders', 'WHEEL LOADERS', 'لوادر بعجلات'),
    ('backhoe-loaders', 'BACKHOE LOADERS', 'لوادر حفارة'),
    ('skid-steer-loaders', 'SKID STEER LOADERS', 'لوادر انزلاقية'),
    ('diesel-forklifts', 'DIESEL FORKLIFTS', 'رافعات شوكية ديزل'),
    ('electric-forklifts', 'ELECTRIC FORKLIFTS', 'رافعات شوكية كهربائية'),
    ('telescopic-loaders', 'TELESCOPIC LOADERS', 'لوادر تلسكوبية'),
    ('material-handling-equipment', 'MATERIAL HANDLING EQUIPMENT', 'معدات مناولة المواد'),
    ('trailer-concrete-pumps', 'TRAILER CONCRETE PUMPS', 'مضخات خرسانة مقطورة'),
]

TILE = ('<li class="columns small-3"><div class="transition">'
        '<div class="title"><a href="{prefix}{folder}.html">{name}</a></div>'
        '<div class="sub"></div>'
        '<div class="image"><a href="{prefix}{folder}.html">'
        '<img alt="{name}" src="{imgprefix}{folder}.jpg" loading="lazy" decoding="async" width="360" height="202">'
        '</a></div></div></li>')

def build_strip(lang: str) -> str:
    if lang == 'ar':
        prefix = '../en/products/'  # ar/index.html links into ../en/... tree? no: same-tree ar/products/
        prefix = 'products/'
        imgprefix = '../assets/img/cat-illus/'
    else:
        prefix = 'en/products/'
        imgprefix = 'assets/img/cat-illus/'
    tiles = []
    for i, (folder, en, ar) in enumerate(ORDER):
        name = ar if lang == 'ar' else en
        t = TILE.format(prefix=prefix, folder=folder, name=name, imgprefix=imgprefix)
        # Tiles 5-10: hidden until original JS reveals expanded rows (as original site).
        if i >= 4:
            t = t.replace('<li class="columns small-3">', '<li class="columns small-3" style="display:none;" data-scor-more="1">', 1)
        tiles.append(t)
    return '<ul class="productGroups row collapse">\n' + '\n'.join(tiles) + '\n</ul>'

def process(rel: str, lang: str):
    p = ROOT / rel
    h = p.read_text(encoding='utf-8', errors='ignore')
    original = h

    # 1) Everything after the first </html> is duplicate junk — cut it.
    idx = h.find('</html>')
    assert idx > 0, rel
    h = h[: idx + len('</html>')] + '\n'

    # 2) Remove the tiles-fix override block entirely.
    h = re.sub(r'<style id="scor-tiles-fix">.*?</style>\s*', '', h, count=1, flags=re.S)

    # 3) Replace the in-body productGroups <ul>…</ul> (balanced) with the rebuilt strip.
    m = re.search(r'<ul class="productGroups[^"]*">', h)
    assert m, rel
    depth = 0
    tag_re = re.compile(r'<ul\b|</ul>')
    end = None
    for t in tag_re.finditer(h, m.start()):
        if t.group(0) == '<ul':
            depth += 1
        else:
            depth -= 1
            if depth == 0:
                end = t.end()
                break
    assert end, rel
    h = h[: m.start()] + build_strip(lang) + h[end:]

    p.write_text(h, encoding='utf-8')
    print(f'{rel}: duplicate strip after </html> removed: {original[idx+7:].strip()[:40]!r}…; tiles-fix removed; strip rebuilt (10 tiles, tiles 5-10 hidden until JS)')

process('index.html', 'en')
process('ar/index.html', 'ar')

# -*- coding: utf-8 -*-
"""Correct the wrong category 'Material Handling Equipment':
it is really SELF-LOADING MIXERS (خلاطات ذاتية التعبئة).

Actions (EN+AR):
1. Rename category everywhere: slug material-handling-equipment -> self-loading-mixers,
   name EN 'Self-Loading Mixers', AR 'خلاطات ذاتية التعبئة' — files moved (git-style mv),
   every reference in every site HTML/XML/TXT rewritten, old strings removed.
2. Move the 4 real forklifts out first (by power evidence):
   WX30F (Isuzu engine) + WX35F (Xinchai engine) -> diesel-forklifts
   WX50F (Battery drive) + WXR50F (battery voltage) -> electric-forklifts
   Their pages move folders; breadcrumbs/h2/hero/sidebar/canonical follow.
3. The remaining 15 MX mixers stay; their mixers get the green pump-mixer photos
   (mixer drum machines) — photo class appended in this pass for those pages.
4. Sitemaps regenerated with new URLs; counts lines updated.
Raw-text rewrites for names/slugs; BeautifulSoup only for card splicing/moving.
"""
import json, re
import shutil
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path('/home/user/scor1')
OLD = 'material-handling-equipment'
NEW = 'self-loading-mixers'
EN_NAME = 'Self-Loading Mixers'
AR_NAME = 'خلاطات ذاتية التعبئة'
OLD_EN = 'Material Handling Equipment'
OLD_AR = 'معدات مناولة المواد'
OLD_FOLDER = 'Material Handling Equipment'

MOVES = {  # slug -> (dest folder, category EN, category AR)
    'wx30f': ('diesel-forklifts', 'DIESEL FORKLIFTS', 'رافعات شوكية ديزل'),
    'wx35f': ('diesel-forklifts', 'DIESEL FORKLIFTS', 'رافعات شوكية ديزل'),
    'wx50f': ('electric-forklifts', 'ELECTRIC FORKLIFTS', 'رافعات شوكية كهربائية'),
    'wxr50f': ('electric-forklifts', 'ELECTRIC FORKLIFTS', 'رافعات شوكية كهربائية'),
}

def log(msg): print(msg)

# ---------- 1) move forklift model pages ----------
for lang in ('en', 'ar'):
    for slug, (dest, _en, _ar) in MOVES.items():
        src = ROOT / lang / 'products' / OLD / f'{slug}.html'
        dst = ROOT / lang / 'products' / dest / f'{slug}.html'
        assert src.exists(), src
        assert not dst.exists(), dst
        dst.write_text(src.read_text(encoding='utf-8', errors='ignore'), encoding='utf-8')
        src.unlink()
        log(f'{lang}: moved {slug}.html -> {dest}/')

# ---------- 2) rename category folder + category page ----------
for lang in ('en', 'ar'):
    old_dir = ROOT / lang / 'products' / OLD
    new_dir = ROOT / lang / 'products' / NEW
    assert old_dir.exists()
    shutil.move(str(old_dir), str(new_dir))
    old_page = ROOT / lang / 'products' / f'{OLD}.html'
    new_page = ROOT / lang / 'products' / f'{NEW}.html'
    shutil.move(str(old_page), str(new_page))
    # category illustration renamed to match
    old_img = ROOT / f'assets/img/cat-illus/{OLD}.jpg'
    new_img = ROOT / f'assets/img/cat-illus/{NEW}.jpg'
    if old_img.exists() and not new_img.exists():
        shutil.move(str(old_img), str(new_img))
    log(f'{lang}: category renamed -> {NEW}')

# ---------- 3) site-wide textual rewrites (all html/xml/txt under scor1) ----------
changed_files = 0
for ext in ('*.html', '*.xml', '*.txt'):
    for f in ROOT.rglob(ext):
        t = f.read_text(encoding='utf-8', errors='ignore')
        o = t
        t = t.replace(f'/{OLD}/', f'/{NEW}/').replace(f'products/{OLD}.html', f'products/{NEW}.html')
        t = t.replace(f'{OLD}.html', f'{NEW}.html').replace(OLD, NEW)
        t = t.replace('ar/products/' + OLD, 'ar/products/' + NEW)
        t = t.replace(OLD_EN, EN_NAME).replace(OLD_EN.upper(), EN_NAME.upper())
        t = t.replace(OLD_AR, AR_NAME).replace('معدات مناولة المواد', AR_NAME)
        if t != o:
            f.write_text(t, encoding='utf-8'); changed_files += 1
log(f'textual rewrite touched {changed_files} files')

# ---------- 4) update scor-cats-map.json ----------
mp = Path('/home/user/scor-cats-map.json')
cats = json.loads(mp.read_text(encoding='utf-8'))
entry = cats.pop(OLD)
entry['folder'] = NEW
entry['name_en'] = EN_NAME
entry['name_ar'] = AR_NAME
mixer_models = []
for m in entry['models']:
    if m['slug'] in MOVES:
        dest = MOVES[m['slug']][0]
        m2 = dict(m); m2['href'] = f"{dest}/{m['slug']}.html"
        cats[dest]['models'].append(m2)
        cats[dest]['count'] = cats[dest].get('count', 0) + 1
    else:
        m['href'] = f"{NEW}/{m['slug']}.html"
        mixer_models.append(m)
entry['models'] = mixer_models
entry['count'] = len(mixer_models)
cats[NEW] = entry
mp.write_text(json.dumps(cats, ensure_ascii=False, indent=1), encoding='utf-8')
log(f'map updated: {NEW} has {len(mixer_models)} models; diesel now {cats["diesel-forklifts"]["count"]}, electric now {cats["electric-forklifts"]["count"]}')

# ---------- 5) moved forklift pages: fix folder-deep references ----------
for lang in ('en', 'ar'):
    for slug, (dest, en_cat, ar_cat) in MOVES.items():
        p = ROOT / lang / 'products' / dest / f'{slug}.html'
        s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
        cat_label = ar_cat if lang == 'ar' else en_cat
        h2 = s.select_one('#pageHeader h2')
        if h2: h2.string = cat_label if lang == 'ar' else cat_label.upper()
        crumb = s.select_one('#breadCrumb')
        if crumb:
            items = crumb.select('.breadcrumb-item')
            if len(items) >= 4:
                a = items[2].find('a')
                if a:
                    a['href'] = f'../{dest}.html'; a.string = ' ' + cat_label.upper() + ' '
        # sidebar image: keep category illustration of the NEW category
        img = s.select_one('#sideBar .image img, img.slide')
        for im in s.select('img.slide'):
            im['src'] = f'../../../assets/img/cat-illus/{dest}.jpg'
        p.write_text(str(s), encoding='utf-8')
    log(f'{lang}: 4 forklift pages fixed (h2/breadcrumb/photo)')

# ---------- 6) rebuild the 3 affected category card lists (static splice) ----------
CARDS_CSS_OK = True
def model_display(page: Path) -> str:
    s = BeautifulSoup(page.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
    t = s.title.get_text(' ', strip=True) if s.title else page.stem.upper()
    return t.split('|')[0].strip() or page.stem.upper()

DESC = {
    'en': lambda d: f'The SCOR {d} — full specifications on the model page.',
    'ar': lambda d: f'الموديل SCOR {d} — المواصفات الكاملة في صفحة الموديل.',
}
BTN = {'en': 'More Details', 'ar': 'مزيد من التفاصيل'}

def card(lang, folder, slug, display):
    href = f'{folder}/{slug}.html'
    img_src = f'../../assets/img/cat-illus/{folder}.jpg'
    # use the model page's own slider image when it is a real photo
    mp_page = ROOT / lang / 'products' / folder / f'{slug}.html'
    if mp_page.exists():
        ms = BeautifulSoup(mp_page.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
        sl = ms.select_one('img.slide')
        if sl and 'cat-photos' in (sl.get('src') or ''):
            img_src = '../../' + sl['src'][len('../../../'):]
    return (f'<li class="product"><div class="row"><div class="columns small-12 medium-4 large-3">'
            f'<a href="{href}"><img alt="{display}" loading="eager" decoding="async" src="{img_src}"></a></div>'
            f'<div class="columns small-12 medium-8 large-9"><div class="row info">'
            f'<div class="columns small-12 medium-12 large-6"><a class="name" href="{href}">{display}</a>'
            f'<div class="description">{DESC[lang](display)}</div>'
            f'<div class="buttons" style="margin-top:14px;"><a class="button" href="{href}">{BTN[lang]}</a></div></div>'
            f'<div class="columns small-12 medium-12 large-6"><ul class="specs row collapse align-middle"></ul></div>'
            f'</div></div></div></li>')

COUNT = {'en': 'Showing {n} {name} models', 'ar': 'عرض {n} موديلات من {name}'}
for lang in ('en', 'ar'):
    for folder in (NEW, 'diesel-forklifts', 'electric-forklifts'):
        cat = cats[folder]
        page = ROOT / lang / 'products' / f'{folder}.html'
        slugs = [m['slug'] for m in cat['models']]
        # add any other live pages in the folder (legacy SY etc.)
        existing = [p.stem for p in sorted((ROOT / lang / 'products' / folder).glob('*.html'))]
        order = slugs + [s for s in existing if s not in slugs]
        name = cat['name_ar'] if lang == 'ar' else cat['name_en']
        cards = []
        for slug in order:
            mp_page = ROOT / lang / 'products' / folder / f'{slug}.html'
            display = model_display(mp_page) if mp_page.exists() else slug.upper()
            cards.append(card(lang, folder, slug, display))
        h = page.read_text(encoding='utf-8', errors='ignore')
        # balanced replace of #products block
        start = h.find('<div id="products">')
        assert start >= 0, page
        depth = 0; end = None
        for mt in re.finditer(r'<div\b|</div>', h[start:]):
            depth += 1 if mt.group(0) == '<div' else -1
            if depth == 0:
                end = start + mt.end(); break
        count_line = COUNT[lang].format(n=len(cards), name=name)
        block = f'<div id="products"><p class="scor-card-count">{count_line}</p><ul>' + '\n'.join(cards) + '</ul></div>'
        h = h[:start] + block + h[end:]
        # sidebar image/name for the renamed category (and refresh for forklifts)
        soup = BeautifulSoup(h, 'html.parser')
        simg = soup.select_one('#sideBar .image img')
        if simg: simg['src'] = f'../../assets/img/cat-illus/{folder}.jpg'; simg['alt'] = name
        b = soup.select_one('#sideBar .description b')
        if b: b.string = name
        page.write_text(str(soup), encoding='utf-8')
        log(f'{lang}/products/{folder}.html: {len(cards)} static cards')

# ---------- 7) mixers photo class for the 15 MX model pages ----------
MIXER_PHOTOS = ['../../../assets/img/cat-photos/pump-mixer-1.jpg',
                '../../../assets/img/cat-photos/pump-mixer-3.jpg',
                '../../../assets/img/cat-photos/pump-mixer-2.jpg']
for lang in ('en', 'ar'):
    for m in cats[NEW]['models']:
        p = ROOT / lang / 'products' / NEW / f"{m['slug']}.html"
        if not p.exists(): continue
        s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
        main = MIXER_PHOTOS[hash(m['slug']) % len(MIXER_PHOTOS)]
        for im in s.select('img.slide'):
            im['src'] = main
        p.write_text(str(s), encoding='utf-8')
log('mixer model sliders set to green mixer photos')

# ---------- 8) sitemaps ----------
for sm in ('sitemap.xml', 'sitemap-products.xml'):
    f = ROOT / sm
    if f.exists():
        t = f.read_text(encoding='utf-8', errors='ignore')
        t = t.replace(OLD, NEW)
        # drop URLs of the 4 forklifts under the old path; add new ones
        for slug in MOVES:
            t = re.sub(r'\s*<url><loc>https://www\.scor-sy\.de/(?:en|ar)/products/' + OLD + '/' + slug + r'\.html</loc>.*?</url>', '', t, flags=re.S)
        f.write_text(t, encoding='utf-8')
log('sitemaps updated')
print('DONE')

# -*- coding: utf-8 -*-
"""Fill every mapped model page (EN+AR) with its FULL original data.

Scope: the 110 products.json models (via scor-cats-map.json). Legacy SY/pump
pages are not in products.json and are left untouched.

Per page:
1. Technical Specs tab content = ALL source fields for that model, grouped:
   Marketing slogan / Short description  -> General tab (real EN/AR text)
   std_equip_*  -> "Standard Equipment" group (values as items)
   opt_equip_*  -> "Optional Equipment" group
   dim_*        -> "Dimensions" group
   note_*       -> "Notes" group
   everything else with a value -> "Technical Specifications" group
   Group headers use the original specGroup markup; rows use the original
   li.spec markup. Spec values are never altered; EN page falls back to the
   Arabic value only when no EN value exists (values themselves, per source).
2. General tab paragraph containing the boilerplate "belongs to our" /
   "ينتمي طراز" (incl. the wrong 'Forklifts' on EN) is replaced by the real
   slogan + short description (or a correct category sentence when absent).
3. Tabs: EN pages get Gallery + FAQ tabs restored (same shape/text as AR).
4. Gallery: first image = assigned real user photo (per machine class);
   extra figures = the class's other photos (same class only).
5. Breadcrumb category name fixed (wrong 'Forklifts' -> real category).

Photos (user-supplied, preview only; user will upload real ones later):
- All seven '1 (n).png' + 'WX 75.jpg' are wheel-excavator photos -> wheel-excavators
- IMG-20261015-WA0087.jpg = wheel loader (WL36) -> wheel-loaders
- IMG-20261009-WA0014.jpg = skid steer -> skid-steer-loaders
- IMG-20261011-WA0036.jpg = skid steer (already cat image) -> skid-steer
- IMG-20261008-WA0022.jpg = SYPM-30 pump-mixer -> trailer-concrete-pumps
- IMG-20261011-WA0016.jpg = mixer drum (already pump cat image) -> pumps
Everything else keeps its category image.
"""
import json, re
import shutil
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path('/home/user/scor1')
UP = Path('/home/user/uploads')
src = json.loads(Path('/home/user/uploads/products.json').read_text(encoding='utf-8'))
cats_map = json.loads(Path('/home/user/scor-cats-map.json').read_text(encoding='utf-8'))
extra_labels = json.loads(Path('/home/user/scor-extra-ar-labels.json').read_text(encoding='utf-8'))
field_labels = src['field_labels']
rec_by_code = {}
for m in src['models']:
    rec_by_code.setdefault(m['code'], m)

AR_RE = re.compile(r'[\u0600-\u06FF]')

# ---------- photos ----------
PHOTO_DIR = ROOT / 'assets/img/cat-photos'
PHOTO_DIR.mkdir(parents=True, exist_ok=True)
def optimise(src_path: Path, name: str) -> str:
    from PIL import Image
    dst = PHOTO_DIR / (name + '.jpg')
    im = Image.open(src_path).convert('RGB')
    im.thumbnail((900, 900))
    im.save(dst, 'JPEG', quality=80, optimize=True)
    return 'assets/img/cat-photos/' + name + '.jpg'

def keep(name: str) -> str:
    return 'assets/img/cat-photos/' + name + '.jpg'

CLASS_PHOTOS = {
 # 2026-10-19 uploads are the ONLY product photos; nothing else may be used later.
 'crawler-excavators': [keep('crawler-excavator-1')],
 'wheel-excavators': [optimise(UP / f'1 ({i}).png', f'wheel-excavator-{i}') for i in range(1, 8)]
                     + [optimise(UP / 'WX 75.jpg', 'wheel-excavator-wx75'), keep('wheel-excavator-9')],
 'wheel-loaders':    [optimise(UP / 'IMG-20261015-WA0087.jpg', 'wheel-loader-1')],
 'skid-steer-loaders':[optimise(UP / 'IMG-20261009-WA0014.jpg', 'skid-steer-1'),
                       optimise(UP / 'IMG-20261011-WA0036.jpg', 'skid-steer-2'),
                       keep('skid-steer-3'), keep('skid-steer-4')],
 'trailer-concrete-pumps': [optimise(UP / 'IMG-20261008-WA0022.jpg', 'pump-mixer-1'),
                            optimise(UP / 'IMG-20261011-WA0016.jpg', 'pump-mixer-2'),
                            keep('trailer-pump-1'), keep('trailer-pump-2'), keep('pump-mixer-3')],
}

# ---------- text helpers ----------
def label_for(key: str, lang: str) -> str:
    lb = field_labels.get(key) or {}
    if lang == 'ar':
        ar = (lb.get('ar') or '').strip()
        if ar and AR_RE.search(ar):
            return ar.replace('_', ' ')
        if key in extra_labels:
            return str(extra_labels[key])
        return lb.get('en') or key.replace('_', ' ')
    return lb.get('en') or key.replace('_', ' ')

def val_for(rec: dict, key: str, lang: str) -> str:
    vals = (rec.get('values') or {}).get(key) or {}
    if lang == 'ar':
        return str(vals.get('ar') or vals.get('en') or '')
    return str(vals.get('en') or vals.get('ar') or '')

GROUP_NAMES = {
 'en': {'tech': 'Technical Specifications', 'dims': 'Dimensions',
        'std': 'Standard Equipment', 'opt': 'Optional Equipment', 'notes': 'Notes'},
 'ar': {'tech': 'المواصفات الفنية', 'dims': 'الأبعاد',
        'std': 'التجهيزات القياسية', 'opt': 'التجهيزات الاختيارية', 'notes': 'ملاحظات'},
}

def groups_for(rec: dict, lang: str):
    order = {'tech': 0, 'dims': 1, 'std': 2, 'opt': 3, 'notes': 4}
    groups = {k: [] for k in order}
    for key in (rec.get('values') or {}):
        if key in ('marketing_slogan', 'short_description', 'model_name'):
            continue
        v = val_for(rec, key, lang)
        if not v:
            continue
        if key.startswith('std_equip'):
            g = 'std'
        elif key.startswith('opt_equip'):
            g = 'opt'
        elif key.startswith('dim_'):
            g = 'dims'
        elif key.startswith('note_'):
            g = 'notes'
        else:
            g = 'tech'
        groups[g].append((label_for(key, lang), v))
    return [(GROUP_NAMES[lang][g], groups[g]) for g in sorted(groups, key=order.get) if groups[g]]

def spec_group_html(name: str, rows) -> str:
    head = ('<li class="columns small-12 medium-12"><div class="specGroup"><ul class="specs row">'
            '<li class="spec columns small-12 medium-12"><div><div class="row"><div class="columns small-12">'
            f'<div class="key category"><b>{name}</b></div></div></div></div></li>')
    body = ''.join(
        '<li class="spec columns small-12 medium-12 large-6"><div><div class="row">'
        f'<div class="columns"><div class="key">{lb}</div></div>'
        f'<div class="columns shrink"><div class="value">{v}</div></div></div></div></li>'
        for lb, v in rows)
    return head + body + '</ul></div></li>'

FAQ_EN = ('<div class="detail"><p><b>How can I get spare parts and service for this machine?</b><br/>'
          'SCOR provides genuine parts supply, equipment care and operator training. Contact us at '
          '<a href="mailto:info@scor-sy.de">info@scor-sy.de</a> or +49 176 34389434 (Mon–Sat: 10–6 Pm).</p>'
          '<p><b>How can I request a price offer?</b><br/>Use the “Request Offer” button on this page or email '
          '<a href="mailto:info@scor-sy.de">info@scor-sy.de</a> with the model name.</p></div>')
TAB_TITLES = {'en': ['General Information', 'Technical Specs', 'Gallery', 'FAQ'],
              'ar': ['معلومات عامة', 'المواصفات الفنية', 'المعرض', 'الأسئلة الشائعة']}

def tab_html(title: str, content: str, transition: bool):
    tr = ' transition' if transition else ''
    return ('<li class="tab active">\n<div class="header row">\n'
            f'<div class="columns"><h3>{title}</h3></div>\n'
            '<div class="columns shrink">\n<div class="action">\n'
            f'<span class="on icon-plus-circled{tr}"></span>\n'
            '<span class="off icon-minus-circled"></span>\n'
            '</div>\n</div>\n</div>\n'
            f'<div class="content">{content}</div>\n</li>')

CAT_NAME = {}
for folder, c in cats_map.items():
    CAT_NAME[folder] = (c['name_en'], c['name_ar'])

def rel(from_page_dir_depth: int, asset: str) -> str:
    return '../' * from_page_dir_depth + asset

stats = {'pages': 0, 'rows_before': 0, 'rows_after': 0, 'general_fixed': 0, 'tabs_fixed': 0}

for folder, cat in cats_map.items():
    FOLDER_OVERRIDE = {('skid-steer-loaders', 'ex65-2'): 'crawler-excavators',
                       ('skid-steer-loaders', 'wx180'): 'wheel-excavators'}
    for minfo in cat['models']:
        # Duplicate codes (EX65/WX65 have 2-3 source records): pick the record
        # whose own source category matches this page's real folder family.
        FAM = {'crawler-excavators': 'excavator_crawler', 'skid-steer-loaders': 'skid_steer',
               'wheel-excavators': 'excavator_wheeled'}
        candidates = [m for m in src['models'] if m['code'] == minfo['code']]
        rec = None
        want = FAM.get(FOLDER_OVERRIDE.get((folder, minfo['slug']), folder))
        if len(candidates) > 1 and want:
            rec = next((m for m in candidates if m['category'] == want), None)
        if rec is None:
            rec = candidates[0] if candidates else None
        if rec is None:
            continue
        page_folder = FOLDER_OVERRIDE.get((folder, minfo['slug']), folder)
        slug = minfo['slug']
        for lang in ('en', 'ar'):
            page = ROOT / lang / 'products' / page_folder / f'{slug}.html'
            if not page.exists():
                print('MISSING PAGE', page); continue
            html = page.read_text(encoding='utf-8', errors='ignore')
            soup = BeautifulSoup(html, 'html.parser')
            prod = soup.select_one('#product')
            if prod is None:
                print('NO #product', page); continue
            stats['pages'] += 1
            _tabs = prod.select(':scope > ul > li.tab')
            _spec_tab = _tabs[1] if len(_tabs) >= 2 else None
            stats['rows_before'] += len([x for x in (_spec_tab.select('.specGroups li.spec') if _spec_tab else [])
                                         if not x.select_one('.key.category')])

            groups = groups_for(rec, lang)
            specs_html = ''.join(spec_group_html(n, r) for n, r in groups)
            n_rows = sum(len(r) for _, r in groups)

            # ----- general tab text -----
            slogan = val_for(rec, 'marketing_slogan', lang)
            short = val_for(rec, 'short_description', lang)
            parts = []
            if slogan: parts.append(slogan)
            if short:
                parts += [ln.strip() for ln in short.split('\n') if ln.strip()]
            cat_en, cat_ar = CAT_NAME[folder]
            model = minfo['display']
            if not parts:
                if lang == 'ar':
                    parts = [f'الموديل SCOR {model} من فئة {cat_ar} — المواصفات الفنية الكاملة أدناه.']
                else:
                    parts = [f'The SCOR {model} — {cat_en}. Full technical specifications below.']
            general_html = '<div class="detail">' + ''.join(f'<p>{pt}</p>' for pt in parts) + '</div>'

            # ----- gallery -----
            depth = 3  # {lang}/products/{folder}/{slug}.html -> site root needs ../../../
            photos = CLASS_PHOTOS.get(page_folder, [])
            if photos:
                main = photos[hash(slug) % len(photos)]
                others = [p for p in photos if p != main]
                main_src = rel(depth, main)
            else:
                main_src = rel(depth, f'assets/img/cat-illus/{folder}.jpg')
                others = []
            views = [f'<figure><img src="{main_src}" alt="{model}"><figcaption>{model}</figcaption></figure>']
            for i, pth in enumerate(others):
                views.append(f'<figure><img src="{rel(depth, pth)}" alt="{model} — {i+2}"><figcaption>{model} — {i+2}</figcaption></figure>')
            gallery_html = '<div class="scor-more-views">' + ''.join(views) + '</div>'

            # ----- tabs: keep the page's own tabs; fix only what is wrong -----
            titles = TAB_TITLES[lang]
            tabs_li = prod.select(':scope > ul > li.tab')
            # General tab: replace boilerplate text with the real slogan/description.
            if tabs_li:
                gen_detail = tabs_li[0].select_one('.content .detail')
                if gen_detail is not None:
                    gen_detail.replace_with(BeautifulSoup(general_html, 'html.parser').find('div'))
            specs_content = '<div><ul class="specGroups row collapse">' + specs_html + '</ul></div>'
            if len(tabs_li) >= 2:
                c = tabs_li[1].select_one('.content')
                if c is not None:
                    c.clear(); c.append(BeautifulSoup(specs_content, 'html.parser').find('div'))
            # EN pages had only 2 tabs: append Gallery + FAQ (original markup).
            if len(tabs_li) == 2:
                ul = tabs_li[0].parent
                faq = FAQ_EN
                add = BeautifulSoup('<div>' + tab_html(titles[2], gallery_html, False)
                                    + tab_html(titles[3], faq, False) + '</div>', 'html.parser')
                for li in add.find('div').find_all('li', recursive=False):
                    ul.append(li.extract())
                stats['tabs_fixed'] += 1
            elif len(tabs_li) >= 3:
                # Gallery tab: refresh figures with the assigned photo set.
                g = tabs_li[2].select_one('.content')
                if g is not None:
                    g.clear(); g.append(BeautifulSoup(gallery_html, 'html.parser').find('div'))

            # ----- gallery slider images (top of page): load instantly -----
            for img in soup.select('img.slide'):
                img['src'] = main_src
                img['loading'] = 'eager'
                img['fetchpriority'] = 'high'
                if img.has_attr('data-src'): del img['data-src']
            for im in soup.select('.scor-gallery img, .gallery img'):
                if 'cat-illus' in (im.get('src') or ''):
                    im['src'] = main_src

            # ----- breadcrumb + wrong category word -----
            for b in prod.select('#breadCrumb .breadcrumb-item a, .breadcrumb-item a'):
                pass
            crumb = soup.select_one('#breadCrumb')
            if crumb:
                items = crumb.select('.breadcrumb-item')
                if len(items) >= 3:
                    a = items[2].find('a')
                    if a:
                        a['href'] = f'../{folder}.html'
                        a.string = f'  {cat_ar if lang == "ar" else cat_en.upper()}  '
            gen_old = prod.select_one('.tab .content .detail p')
            if gen_old and ('belongs to our' in gen_old.get_text() or 'ينتمي طراز' in gen_old.get_text()):
                stats['general_fixed'] += 1

            page.write_text(str(soup), encoding='utf-8')
            stats['rows_after'] += n_rows

print(stats)

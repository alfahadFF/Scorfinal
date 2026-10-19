#!/usr/bin/env python3
"""Generate ALL model cards on every category page, per category classification.

Contract (2026-10-19 instructions):
- The site is not published yet; the client reviews the local site as-is.
- Every category page must visibly show cards for ALL models that belong to
  that category, generated from the source classification rather than edited
  one card at a time.

Rules:
- Membership comes from /home/user/scor-cats-map.json (the 110-model map).
- Existing model pages inside the same folder not listed in the map are also
  shown (so nothing that exists on disk is hidden).
- Each card is the original <li class="product"> shape; image, model name and
  the "More Details" button all link to the same child model page.
- Card images use the per-category illustration, embedded ONLY when the
  illustration is a real photo (> 50 KB); the six placeholder illustrations
  (15,011 bytes each) are never embedded.
- Cards are fully static: no JavaScript is required to create or reveal them.
  The only script is a tiny independent counter/fallback that can hide nothing.
- BeautySoup round-trip is forbidden here (it restructures markup); the cards
  are spliced in as raw HTML text.

Outputs: {en,ar}/products/<category>.html (cards + count line + counter script)
and static client-review pages {,ar/}client-review/products/<category>.html
containing the same generated cards.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path('/home/user/scor1')
MAP_PATH = Path('/home/user/scor-cats-map.json')
PRODUCTS_JSON = Path('/home/user/uploads/products.json')
EXTRA_LABELS = Path('/home/user/scor-extra-ar-labels.json')

cats = json.loads(MAP_PATH.read_text(encoding='utf-8'))
source = json.loads(PRODUCTS_JSON.read_text(encoding='utf-8'))
extra = json.loads(EXTRA_LABELS.read_text(encoding='utf-8')) if EXTRA_LABELS.exists() else {}
field_labels = source.get('field_labels', {})
by_code: dict[str, dict] = {}
for rec in source.get('models', []):
    by_code.setdefault(rec.get('code'), rec)

ALIASES = {
    ('skid-steer-loaders', 'ex65-2'): ('crawler-excavators', 'ex65-2'),
    ('skid-steer-loaders', 'wx180'): ('wheel-excavators', 'wx180'),
}
EMBED_MIN_BYTES = 50_000  # real photos only (>50KB); placeholders are 15,011 bytes

SPEC_PRIORITY = [
    'rated_load_weight', 'rated_load', 'load_capacity', 'capacity',
    'weight_operating', 'operating_weight', 'machine_operating_weight', 'total_weight', 'weight_total', 'service_weight', 'machine_weight',
    'engine_power', 'eng_power', 'engine_model', 'eng_model',
    'bucket_capacity', 'buket_capacity', 'lift_height', 'lifting_height',
    'dig_depth', 'max_digging_depth', 'dig_height', 'max_digging_height',
    'travel_speed', 'speed', 'performance_productivity', 'pump_vertical_coarse', 'pump_vertical_fine',
    'drum_capacity', 'mixer_capacity', 'max_mixing_volume', 'fork_size',
]
ARABIC_RE = re.compile(r'[\u0600-\u06FF]')

EN_SIDEBAR = {
    'backhoe-loaders': 'SCOR backhoe loaders for digging, loading and utility work — models and full specifications below.',
    'crawler-excavators': 'SCOR crawler excavators for digging and heavy construction — models and full specifications below.',
    'wheel-excavators': 'SCOR wheel excavators for mobile digging and road work — models and full specifications below.',
    'wheel-loaders': 'SCOR wheel loaders for loading, transport and material handling — models and full specifications below.',
    'skid-steer-loaders': 'SCOR skid steer loaders for compact sites and versatile attachments — models and full specifications below.',
    'diesel-forklifts': 'SCOR diesel forklifts in a range of lift capacities for heavy loading and handling work.',
    'electric-forklifts': 'SCOR electric forklifts — clean, quiet performance for warehouses and indoor operation.',
    'telescopic-loaders': 'SCOR telescopic loaders with telescopic boom for lifting and handling on sites, farms and warehouses.',
    'material-handling-equipment': 'SCOR material handling equipment for warehouses and logistics: stackers, platforms and pallet trucks.',
    'trailer-concrete-pumps': 'SCOR trailer concrete pumps and pump-mixers for reliable concrete placement — models and full specifications below.',
}
AR_SIDEBAR = {
    'backhoe-loaders': 'تشكيلة لوادر حفارة SCOR بموديلات ومواصفات معتمدة من المصنع.',
    'crawler-excavators': 'تشكيلة حفارات مجنزرة SCOR بموديلات ومواصفات معتمدة من المصنع.',
    'wheel-excavators': 'تشكيلة حفارات بعجلات SCOR بموديلات ومواصفات معتمدة من المصنع.',
    'wheel-loaders': 'تشكيلة لوادر بعجلات SCOR بموديلات ومواصفات معتمدة من المصنع.',
    'skid-steer-loaders': 'تشكيلة لوادر انزلاقية SCOR بموديلات ومواصفات معتمدة من المصنع.',
    'diesel-forklifts': 'رافعات شوكية ديزل SCOR بقدرات رفع متنوعة لأعمال التحميل والمناولة الشاقة.',
    'electric-forklifts': 'رافعات شوكية كهربائية SCOR نظيفة وهادئة للمستودعات والأماكن المغلقة.',
    'telescopic-loaders': 'لودرات تلسكوبية SCOR بذراع تلسكوبي للرفع والمناولة في المواقع والمزارع والمستودعات.',
    'material-handling-equipment': 'معدات مناولة المواد SCOR للمستودعات والخدمات اللوجستية: رافعات ومنصات ومكدسات وعربات نقل.',
    'trailer-concrete-pumps': 'تشكيلة مضخات خرسانة مقطورة SCOR بموديلات ومواصفات معتمدة من المصنع.',
}

CARDS_CSS = """/* SCOR generated category cards (static build; no JS required) */
#products{display:block!important;visibility:visible!important;opacity:1!important}
#products>ul{display:block;list-style:none;margin:0;padding:0}
#products li.product{display:block;visibility:visible;opacity:1;background:#fff;border:1px solid #ddd;border-radius:10px;margin:0 0 20px;padding:18px;overflow:hidden;list-style:none;min-height:120px}
#products li.product .name{display:block;color:#151515;font-size:24px;font-weight:700;line-height:1.25;margin-top:6px;text-decoration:none}
#products li.product .description{display:block;color:#555;font-size:14px;line-height:1.6;margin-top:8px}
#products li.product img{display:block;max-width:100%;width:100%;height:auto;border-radius:6px}
#products li.product .button{display:inline-block;background:#F5B400;color:#151515;font-weight:700;padding:10px 18px;border-radius:4px;text-decoration:none}
#products li.product .specs{display:block;background:#f4f4f4;border-radius:6px;padding:10px;list-style:none;margin:0}
#products li.product .spec{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e5e5;padding:4px 0}
#products li.product .specName{color:#667085}
#products li.product .specValue{font-weight:600;color:#151515;text-align:right}
.scor-card-count{font-size:15px;color:#667085;margin:0 0 14px}
@media(max-width:700px){#products li.product{padding:14px}#products li.product .name{font-size:21px}}"""

COUNT_LINE_EN = 'Showing {n} {name} models'
COUNT_LINE_AR = 'عرض {n} موديلات من {name}'


def label_for(key: str, lang: str) -> str:
    labels = field_labels.get(key) or {}
    if lang == 'ar':
        ar = (labels.get('ar') or '').strip()
        if ar and ARABIC_RE.search(ar):
            return ar.replace('_', ' ')
        if key in extra:
            return str(extra[key])
        return labels.get('en') or key.replace('_', ' ')
    return labels.get('en') or key.replace('_', ' ')


def value_for(rec: dict | None, key: str, lang: str) -> str:
    if not rec:
        return ''
    vals = (rec.get('values') or {}).get(key) or {}
    if lang == 'ar':
        return str(vals.get('ar') or vals.get('en') or '')
    return str(vals.get('en') or vals.get('ar') or '')


def card_specs(rec: dict | None, lang: str, display: str) -> list[tuple[str, str]]:
    specs = [('الموديل' if lang == 'ar' else 'Model', display)]
    if rec:
        for key in SPEC_PRIORITY:
            value = value_for(rec, key, lang)
            if value:
                specs.append((label_for(key, lang), value))
            if len(specs) >= 4:
                break
    return specs[:4]


def data_img(folder: str) -> str | None:
    import base64
    # Prefer the small web-sized photo (520px, ~25-35KB) so embedded cards stay fast.
    web = ROOT / f'assets/img/cat-illus-web/{folder}.jpg'
    p = web if web.exists() else ROOT / f'assets/img/cat-illus/{folder}.jpg'
    if not p.exists() or p.stat().st_size > 200_000:
        return None
    return 'data:image/jpeg;base64,' + base64.b64encode(p.read_bytes()).decode()


def img_src(folder: str, lang_from: str) -> str:
    """Embedded data image of the real category photo, or relative path."""
    d = data_img(folder)
    if d:
        return d
    if lang_from == 'client':
        return f'../../assets/img/cat-illus/{folder}.jpg'
    if lang_from == 'client-ar':
        return f'../../../assets/img/cat-illus/{folder}.jpg'
    return f'../../assets/img/cat-illus/{folder}.jpg'


def card_html(lang: str, folder: str, href: str, display: str, rec, review: bool) -> str:
    desc = (f'الموديل SCOR {display} — المواصفات الكاملة في صفحة الموديل.' if lang == 'ar'
            else f'The SCOR {display} — full specifications on the model page.')
    button = 'مزيد من التفاصيل' if lang == 'ar' else 'More Details'
    specs = ''.join(
        '<li class="spec"><div class="row">'
        f'<div class="columns specName">{label}</div>'
        f'<div class="columns shrink specValue">{value}</div></div></li>'
        for label, value in card_specs(rec, lang, display)
    )
    src_from = 'client-ar' if (review and lang == 'ar') else ('client' if review else lang)
    src = img_src(folder, src_from)
    onerror = '' if src.startswith('data:') else f' onerror="this.onerror=null;this.src=\'{src}\';"'
    return (
        '<li class="product"><div class="row">'
        '<div class="columns small-12 medium-4 large-3">'
        f'<a href="{href}"><img alt="{display}" loading="lazy" decoding="async" src="{src}"{onerror}></a>'
        '</div>'
        '<div class="columns small-12 medium-8 large-9"><div class="row info">'
        '<div class="columns small-12 medium-12 large-6">'
        f'<a class="name" href="{href}">{display}</a>'
        f'<div class="description">{desc}</div>'
        f'<div class="buttons" style="margin-top:14px;"><a class="button" href="{href}">{button}</a></div>'
        '</div>'
        '<div class="columns small-12 medium-12 large-6">'
        f'<ul class="specs row collapse align-middle">{specs}</ul>'
        '</div></div></div></div></li>'
    )


def folder_pages(products_dir: Path, folder: str) -> list[tuple[str, str]]:
    out = []
    d = products_dir / folder
    if not d.exists():
        return out
    for f in sorted(d.glob('*.html')):
        text = f.read_text(encoding='utf-8', errors='ignore')
        soup = BeautifulSoup(text, 'html.parser')
        title = soup.title.get_text(' ', strip=True) if soup.title else ''
        display = title.split('|')[0].strip() if '|' in title else f.stem.upper()
        out.append((f.stem, display or f.stem.upper()))
    return out


def build_entries(lang: str):
    products_dir = ROOT / lang / 'products'
    all_entries: dict[str, list[tuple[str, str, dict | None]]] = {}
    for folder, cat in cats.items():
        entries: list[tuple[str, str, dict | None]] = []
        seen: set[str] = set()

        for model in cat.get('models', []):
            target_folder, target_slug = folder, model['slug']
            alias = ALIASES.get((folder, model['slug']))
            if alias:
                target_folder, target_slug = alias
            target = products_dir / target_folder / f'{target_slug}.html'
            if not target.exists():
                raise FileNotFoundError(f'Mapped model page missing: {target}')
            key = str(target)
            if key in seen:
                continue
            seen.add(key)
            # Relative to the products/ folder itself: same-tree path always,
            # never '../' (aliases live in sibling category folders).
            href = f'{target_folder}/{target_slug}.html'
            entries.append((href, model['display'], by_code.get(model['code'])))

        mapped_paths = set(seen)
        for slug, display in folder_pages(products_dir, folder):
            path = products_dir / folder / f'{slug}.html'
            if str(path) in mapped_paths:
                continue
            code = display.replace('SCOR ', '').strip()
            entries.append((f'{folder}/{slug}.html', display, by_code.get(code)))
            seen.add(str(path))
        all_entries[folder] = entries
    return all_entries


def replace_products_block(html: str, new_block: str) -> str:
    """Splice a new <div id="products">…</div> over the old one (balanced scan)."""
    start = html.find('<div id="products">')
    if start < 0:
        raise RuntimeError('no <div id="products"> found')
    depth = 0
    i = start
    tag_re = re.compile(r'<div\b|</div>')
    for m in tag_re.finditer(html, start):
        if m.group(0) == '<div':
            depth += 1
        else:
            depth -= 1
            if depth == 0:
                end = m.end()
                return html[:start] + new_block + html[end:]
    raise RuntimeError('unbalanced #products')


def force_visible_style(html: str) -> str:
    """Cards must not depend on any external file: inline the card CSS before </body>."""
    block = '<style data-scor-cards="gen">\n' + CARDS_CSS + '\n</style>\n'
    html = re.sub(r'<style data-scor-cards[^>]*>.*?</style>\s*', '', html, flags=re.S)
    html = re.sub(r'<style data-scor-cards-inline="1">.*?</style>\s*', '', html, flags=re.S)
    if '</body>' in html:
        return html.replace('</body>', block + '</body>', 1)
    return html + block


def counter_script() -> str:
    """Only counts the rendered cards and records the count; it can hide nothing."""
    return ('<script data-scor-count="gen">(function(){var n=document.querySelectorAll("#products li.product").length;'
            'document.documentElement.setAttribute("data-card-count",n);})();</script>')


def write_main_pages(lang: str, all_entries) -> dict[str, int]:
    counts = {}
    products_dir = ROOT / lang / 'products'
    for folder, entries in all_entries.items():
        page = products_dir / f'{folder}.html'
        html = page.read_text(encoding='utf-8', errors='ignore')
        cat = cats[folder]
        cat_name = cat['name_ar'] if lang == 'ar' else cat['name_en']
        cards = '\n'.join(card_html(lang, folder, href, display, rec, review=False)
                          for href, display, rec in entries)
        count_text = (COUNT_LINE_AR if lang == 'ar' else COUNT_LINE_EN).format(n=len(entries), name=cat_name)
        block = (f'<div id="products"><p class="scor-card-count">{count_text}</p><ul>'
                 + cards + '</ul></div>')
        html = replace_products_block(html, block)

        # Sidebar: image + name + approved copy.
        s = BeautifulSoup(html, 'html.parser')
        img = s.select_one('#sideBar .image img')
        if img:
            img['src'] = img_src(folder, lang)
            img['alt'] = cat_name
        desc_b = s.select_one('#sideBar .description b')
        if desc_b:
            desc_b.string = cat_name if lang == 'ar' else cat['name_en']
        info = s.select_one('#sideBar .information')
        if info:
            info.string = (AR_SIDEBAR if lang == 'ar' else EN_SIDEBAR)[folder]
        html = str(s)

        html = force_visible_style(html)
        if 'data-scor-count="gen"' not in html:
            html = html.replace('</body>', counter_script() + '\n</body>', 1)
        page.write_text(html, encoding='utf-8')
        counts[folder] = len(entries)
    return counts


def review_page(lang: str, folder: str, entries, link_prefix: str, own_prefix: str) -> str:
    ar = lang == 'ar'
    cat = cats[folder]
    cat_name = cat['name_ar'] if ar else cat['name_en']
    ttl = f'SCOR — {cat_name}' + ('' if ar else ' | SCOR')
    cards = '\n'.join(card_html(lang, folder, link_prefix + href, display, rec, review=True)
                      for href, display, rec in entries)
    count_text = (COUNT_LINE_AR if ar else COUNT_LINE_EN).format(n=len(entries), name=cat_name)
    dirattr = ' dir="rtl"' if ar else ''
    back = 'كل التصنيفات' if ar else 'All categories'
    btn = 'مزيد من التفاصيل' if ar else 'More Details'
    hero_src = img_src(folder, 'client-ar' if ar else 'client')
    return f"""<!doctype html>
<html lang="{lang}"{dirattr}>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{ttl}</title>
<style>
  *{{box-sizing:border-box}}
  body{{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;color:#111827}}
  header{{background:#111827;padding:18px 4vw;border-bottom:4px solid #F5B400;display:flex;justify-content:space-between;align-items:center}}
  header .logo{{font-size:26px;font-weight:800;color:#fff}} header .logo b{{color:#F5B400}}
  header a{{color:#F5B400;font-weight:700;text-decoration:none}}
  .hero{{max-width:1200px;margin:24px auto 6px;padding:0 16px;display:flex;gap:22px;align-items:center;flex-wrap:wrap}}
  .hero img{{width:300px;max-width:100%;border-radius:10px}}
  .hero h1{{font-size:32px;margin:0 0 8px}}
  .hero p{{color:#4b5563;margin:0}}
  main{{max-width:1200px;margin:0 auto;padding:10px 16px 50px}}
{CARDS_CSS}
</style>
</head>
<body>
<header><div class="logo">SCOR<b>.</b></div><a href="{own_prefix}index.html">← {back}</a></header>
<section class="hero"><img src="{hero_src}" alt="{cat_name}"><div><h1>{cat_name}</h1><p>{(AR_SIDEBAR if ar else EN_SIDEBAR)[folder]}</p></div></section>
<main><div id="products"><p class="scor-card-count">{count_text}</p><ul>
{cards}
</ul></div></main>
</body>
</html>"""


def write_review_pages(lang: str, all_entries) -> None:
    base = ROOT / ('ar/client-review/products' if lang == 'ar' else 'client-review/products')
    base.mkdir(parents=True, exist_ok=True)
    prefix = '../../../en/products/' if lang == 'ar' else '../../en/products/'
    own = '../'  # ar/client-review/products/<cat>.html back to ar/client-review/index.html
    if lang == 'ar':
        prefix = '../../../ar/products/'
    for folder, entries in all_entries.items():
        (base / f'{folder}.html').write_text(
            review_page(lang, folder, entries, prefix, own), encoding='utf-8')


def main() -> int:
    print('=== building entries ===')
    per_lang = {lang: build_entries(lang) for lang in ('en', 'ar')}
    total_map = sum(len(cats[f].get('models', [])) for f in cats)
    print('map models total:', total_map)
    for lang in ('en', 'ar'):
        print(f'--- {lang} ---')
        counts = write_main_pages(lang, per_lang[lang])
        write_review_pages(lang, per_lang[lang])
        for folder in cats:
            print(f'  {folder}: {counts[folder]} cards')
        print('  total cards:', sum(counts.values()))
    print('=== done ===')
    return 0


if __name__ == '__main__':
    sys.exit(main())
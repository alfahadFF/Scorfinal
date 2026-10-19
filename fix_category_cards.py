#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Rebuild every EN/AR category page product list so each category visibly shows
its model cards with working detail links.

Rules:
- Source of model membership: /home/user/scor-cats-map.json (110-product map).
- Existing live model pages inside the same folder are preserved and included.
- Missing pages represented by the current folder's same-code pages are remapped
  (EX65 duplicate -> crawler EX65-2; WX180 -> wheel-excavators WX180; MX40 variants
  -> mx40 and mx40-2 without overwriting either).
- Product pages are never overwritten if they already exist; missing MX40-2 is
  created by cloning MX40 and changing its visible model title/code only where
  the page title/name itself identifies MX40.
- Cards use the original #products > ul > li.product structure and point image,
  model name and More Details button to the same child model page.
- Card images use relative ../../assets paths so they work via live server and
  the actual hosted site (not the workspace file viewer).
"""
from __future__ import annotations

import json
import re
import shutil
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path('/home/user/scor1')
MAP_PATH = Path('/home/user/scor-cats-map.json')
PRODUCTS_JSON = Path('/home/user/uploads/products.json')
EXTRA_LABELS = Path('/home/user/scor-extra-ar-labels.json')

cats = json.loads(MAP_PATH.read_text(encoding='utf-8'))
source = json.loads(PRODUCTS_JSON.read_text(encoding='utf-8'))
extra_labels = json.loads(EXTRA_LABELS.read_text(encoding='utf-8')) if EXTRA_LABELS.exists() else {}
field_labels = source.get('field_labels', {})
by_code_all = {}
for rec in source.get('models', []):
    by_code_all.setdefault(rec.get('code'), []).append(rec)

# We deliberately pick the first source record for a duplicate code.
by_code = {code: recs[0] for code, recs in by_code_all.items()}

# Existing destination for map entries that currently resolve to another page.
ALIASES = {
    ('skid-steer-loaders', 'ex65-2'): ('crawler-excavators', 'ex65-2', 'EX65'),
    ('skid-steer-loaders', 'wx180'): ('wheel-excavators', 'wx180', 'WX180'),
}

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

# Sidebar copy: use the approved, category-appropriate descriptions only.
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

def label_for(key: str, lang: str) -> str:
    labels = field_labels.get(key) or {}
    if lang == 'ar':
        ar = labels.get('ar') or ''
        if ar and ARABIC_RE.search(ar):
            return ar.replace('_', ' ')
        if key in extra_labels:
            return extra_labels[key]
        en = labels.get('en') or key.replace('_', ' ')
        return en
    return labels.get('en') or key.replace('_', ' ')

def value_for(rec: dict | None, key: str, lang: str) -> str:
    if not rec:
        return ''
    vals = (rec.get('values') or {}).get(key) or {}
    if lang == 'ar':
        return vals.get('ar') or vals.get('en') or ''
    return vals.get('en') or vals.get('ar') or ''

def card_specs(rec: dict | None, lang: str, display: str) -> list[tuple[str, str]]:
    specs: list[tuple[str, str]] = []
    specs.append(('الموديل' if lang == 'ar' else 'Model', display))
    if rec:
        used = set()
        for key in SPEC_PRIORITY:
            if key in used:
                continue
            value = value_for(rec, key, lang)
            if value:
                specs.append((label_for(key, lang), str(value)))
                used.add(key)
            if len(specs) >= 4:
                break
    return specs[:4]

def existing_folder_models(products_dir: Path, folder: str) -> list[dict]:
    folder_dir = products_dir / folder
    out = []
    if not folder_dir.exists():
        return out
    for f in sorted(folder_dir.glob('*.html')):
        text = f.read_text(encoding='utf-8', errors='ignore')
        soup = BeautifulSoup(text, 'html.parser')
        title = soup.title.get_text(' ', strip=True) if soup.title else ''
        display = title.split('|')[0].strip() if '|' in title else f.stem.upper()
        if not display:
            display = f.stem.upper()
        out.append({'slug': f.stem, 'display': display, 'path': f})
    return out

def card_html(lang: str, folder: str, href: str, display: str, rec: dict | None) -> str:
    image = f'../../assets/img/cat-illus/{folder}.jpg'
    desc_en = f'The SCOR {display} — full specifications on the model page.'
    desc_ar = f'الموديل SCOR {display} — المواصفات الكاملة في صفحة الموديل.'
    desc = desc_ar if lang == 'ar' else desc_en
    button = 'مزيد من التفاصيل' if lang == 'ar' else 'More Details'
    specs = card_specs(rec, lang, display)
    specs_html = ''.join(
        '<li class="spec columns small-12"><div class="row">'
        f'<div class="columns specName">{label}</div>'
        f'<div class="columns shrink specValue">{value}</div>'
        '</div></li>'
        for label, value in specs
    )
    return (
        '<li class="product"><div class="row">'
        '<div class="columns small-12 medium-4 large-3">'
        f'<a href="{href}"><img alt="{display}" loading="lazy" decoding="async" src="{image}"></a>'
        '</div>'
        '<div class="columns small-12 medium-8 large-9"><div class="row info">'
        '<div class="columns small-12 medium-12 large-6">'
        f'<a class="name" href="{href}">{display}</a>'
        f'<div class="description">{desc}</div>'
        f'<div class="buttons" style="margin-top:14px;"><a class="button" href="{href}">{button}</a></div>'
        '</div>'
        '<div class="columns small-12 medium-12 large-6">'
        f'<ul class="specs row collapse align-middle">{specs_html}</ul>'
        '</div></div></div></div></li>'
    )

def ensure_mx40_2(lang: str) -> None:
    products_dir = ROOT / lang / 'products' / 'material-handling-equipment'
    src = products_dir / 'mx40.html'
    dst = products_dir / 'mx40-2.html'
    if dst.exists() or not src.exists():
        return
    text = src.read_text(encoding='utf-8', errors='ignore')
    # Only alter page identity (title / H1 / canonical-ish slug), not specifications:
    # the two MX40 source records share the published spec values, and inventing
    # different values is forbidden.
    text = text.replace('mx40.html', 'mx40-2.html')
    text = text.replace('MX40-هيكل-كامل', 'MX40-هيكل-كامل')
    text = text.replace('MX40-مفصلية', 'MX40-هيكل-كامل')
    # If the cloned page displays the articulated variant name, normalize the
    # visible page title to the full-frame variant name from the map.
    text = re.sub(r'(<title>)\s*MX40[^<]*?(</title>)', r'\1MX40-هيكل-كامل | SCOR\2', text, count=1)
    dst.write_text(text, encoding='utf-8')

def repair_model_breadcrumbs_and_category_links(lang: str) -> int:
    """Make child model pages breadcrumb back to their own parent category.
    Only changes anchors inside #breadCrumb; avoids touching specs/content.
    """
    changed = 0
    products_dir = ROOT / lang / 'products'
    cat_names_en = {folder: cat['name_en'] for folder, cat in cats.items()}
    cat_names_ar = {folder: cat['name_ar'] for folder, cat in cats.items()}
    for folder in cats:
        folder_dir = products_dir / folder
        if not folder_dir.exists():
            continue
        for page in folder_dir.glob('*.html'):
            soup = BeautifulSoup(page.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
            crumb = soup.select_one('#breadCrumb')
            if not crumb:
                continue
            items = crumb.select('.breadcrumb-item')
            if len(items) < 3:
                continue
            target = items[2]  # Home > Products > CATEGORY > Model
            a = target.find('a')
            if not a:
                continue
            label = cat_names_ar[folder] if lang == 'ar' else cat_names_en[folder].upper()
            href = f'../{folder}.html'
            label_changed = a.get_text(' ', strip=True) != label
            href_changed = a.get('href') != href
            if label_changed or href_changed:
                a['href'] = href
                a.clear()
                span = soup.new_tag('span')
                span.string = f'\n                        {label}                    '
                a.append(span)
                changed += 1
            page.write_text(str(soup), encoding='utf-8') if (label_changed or href_changed) else None
    return changed

def main() -> None:
    ensure_mx40_2('en')
    ensure_mx40_2('ar')

    summary = []
    for lang in ('en', 'ar'):
        products_dir = ROOT / lang / 'products'
        for folder, cat in cats.items():
            page_path = products_dir / f'{folder}.html'
            if not page_path.exists():
                raise FileNotFoundError(page_path)
            entries = []
            seen_targets = set()

            # Canonical map models, in map order.
            for model in cat.get('models', []):
                slug = model['slug']
                target_folder, target_slug, display = folder, slug, model['display']
                alias = ALIASES.get((folder, slug))
                if alias:
                    target_folder, target_slug, display = alias
                target_rel = f'{target_folder}/{target_slug}.html'
                target_abs = products_dir / target_folder / f'{target_slug}.html'
                if not target_abs.exists():
                    raise FileNotFoundError(f'Mapped model page missing: {target_abs}')
                href = target_rel if target_folder == folder else f'../{target_rel}'
                # On the destination category page the same model appears without ../.
                if target_folder != folder:
                    href = target_rel.replace(f'{target_folder}/', f'../{target_folder}/', 1) if False else f'../{target_folder}/{target_slug}.html'
                key = str(target_abs)
                if key in seen_targets:
                    continue
                seen_targets.add(key)
                entries.append((href, display, by_code.get(model['code'])))

            # Every other live HTML page physically inside the folder.
            mapped_targets = set(seen_targets)
            for extra in existing_folder_models(products_dir, folder):
                key = str(extra['path'])
                if key in mapped_targets:
                    continue
                href = f"{folder}/{extra['slug']}.html"
                code_guess = extra['display'].replace('SCOR ', '').strip()
                entries.append((href, extra['display'], by_code.get(code_guess)))
                seen_targets.add(key)

            html = page_path.read_text(encoding='utf-8', errors='ignore')
            soup = BeautifulSoup(html, 'html.parser')
            products = soup.select_one('#products')
            if products is None:
                raise RuntimeError(f'#products missing in {page_path}')

            # Normalize sidebar copy for generic/wrong forklift text on non-forklift pages.
            sidebar_info = soup.select_one('#sideBar .information')
            if sidebar_info:
                sidebar_info.string = (AR_SIDEBAR if lang == 'ar' else EN_SIDEBAR)[folder]
            sidebar_desc = soup.select_one('#sideBar .description b')
            if sidebar_desc:
                sidebar_desc.string = cat['name_ar'] if lang == 'ar' else cat['name_en']
            sidebar_img = soup.select_one('#sideBar .image img')
            if sidebar_img:
                sidebar_img['src'] = f'../../assets/img/cat-illus/{folder}.jpg'
                sidebar_img['alt'] = cat['name_ar'] if lang == 'ar' else cat['name_en']

            cards = '\n'.join(card_html(lang, folder, href, display, rec) for href, display, rec in entries)
            replacement = BeautifulSoup(f'<div id="products"><ul>\n{cards}\n</ul></div>', 'html.parser').select_one('#products')
            products.replace_with(replacement)

            page_path.write_text(str(soup), encoding='utf-8')
            summary.append((lang, folder, len(entries)))

    breadcrumb_changes = repair_model_breadcrumbs_and_category_links('en') + repair_model_breadcrumbs_and_category_links('ar')

    # A self-contained CSS safety net ensures cards remain visible even if the
    # legacy bundle CSS/JS fails to load in the user's browser. It is appended
    # as a separate file rather than scattering inline styles through templates.
    css_path = ROOT / 'assets/css/scor-category-cards.css'
    css_path.write_text('''/* SCOR category model cards: visibility safety net */
#products{display:block!important;visibility:visible!important;opacity:1!important}
#products>ul{display:block!important;list-style:none;margin:0;padding:0}
#products li.product{display:block!important;visibility:visible!important;opacity:1!important;background:#fff;border:1px solid #ddd;border-radius:10px;margin:0 0 20px;padding:18px;overflow:hidden}
#products li.product .name{display:block!important;color:#151515!important;font-size:24px!important;font-weight:700!important;line-height:1.25!important;margin-top:6px}
#products li.product .description{display:block!important;color:#555!important;font-size:14px!important;line-height:1.6!important;margin-top:8px}
#products li.product img{display:block!important;max-width:100%!important;width:100%!important;height:auto!important;border-radius:6px}
#products li.product .button{display:inline-block!important;background:#F5B400!important;color:#151515!important;font-weight:700!important;padding:10px 18px!important;border-radius:4px!important;text-decoration:none!important}
#products li.product .specs{display:block!important;background:#f4f4f4!important;border-radius:6px;padding:10px!important;list-style:none!important;margin:0!important}
@media(max-width:700px){#products li.product{padding:14px}#products li.product .name{font-size:21px!important}}
''', encoding='utf-8')

    # Link the safety stylesheet from category pages only (not model pages).
    for lang in ('en', 'ar'):
        for folder in cats:
            p = ROOT / lang / 'products' / f'{folder}.html'
            text = p.read_text(encoding='utf-8', errors='ignore')
            link = '<link rel="stylesheet" href="../../assets/css/scor-category-cards.css">'
            if 'scor-category-cards.css' not in text:
                text = text.replace('</head>', link + '\n</head>', 1)
                p.write_text(text, encoding='utf-8')

    print('Rebuilt category pages:')
    for lang, folder, count in summary:
        print(f'  {lang}/products/{folder}.html -> {count} cards')
    print(f'Model breadcrumb/category corrections: {breadcrumb_changes}')
    print(f'Created CSS safety net: {css_path}')

if __name__ == '__main__':
    main()

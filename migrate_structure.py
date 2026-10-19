#!/usr/bin/env python3
"""Restructure scor1: europe-EN -> en, ar/europe-EN -> ar (flatten), create de/tr,
rewrite all references, strip HMK pages/cards, de-link download buttons."""
import os, re, glob, shutil, json

ROOT = '/home/user/scor1'
os.chdir(ROOT)
log = []

# ---------- 1) move trees ----------
os.makedirs('en', exist_ok=True)
for item in os.listdir('europe-EN'):
    shutil.move(os.path.join('europe-EN', item), os.path.join('en', item))
os.rmdir('europe-EN')
log.append('europe-EN/* -> en/')

for item in os.listdir('ar/europe-EN'):
    shutil.move(os.path.join('ar/europe-EN', item), os.path.join('ar', item))
os.rmdir('ar/europe-EN')
log.append('ar/europe-EN/* -> ar/ (flattened)')

for junk in ['europe-EN.html', 'ar/europe-EN.html']:
    if os.path.exists(junk):
        os.remove(junk)
        log.append('deleted legacy regional page ' + junk)

for lang, title, note in [
    ('de', 'SCOR — Deutsche Version', 'Die deutsche Version dieser Website wird derzeit vorbereitet.'),
    ('tr', 'SCOR — Türkçe Sürüm', 'Bu web sitesinin Türkçe sürümü hazırlanmaktadır.'),
]:
    os.makedirs(lang, exist_ok=True)
    p = os.path.join(lang, 'index.html')
    if not os.path.exists(p):
        open(p, 'w', encoding='utf-8').write(f'''<!DOCTYPE html>
<html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title></head>
<body style="margin:0;font-family:Arial,sans-serif;background:#151515;color:#fff;text-align:center;padding:80px 20px">
<div style="color:#F5B400;font-size:34px;font-weight:bold;letter-spacing:2px">SCOR</div>
<p style="color:#ddd;max-width:520px;margin:22px auto;line-height:1.8">{note}</p>
<a href="../index.html" style="display:inline-block;background:#F5B400;color:#151515;font-weight:bold;padding:12px 26px;border-radius:6px;text-decoration:none">SCOR — Home</a>
</body></html>''')
        log.append(f'created {lang}/index.html placeholder')

# remove the misleading proof page
if os.path.isdir('/home/user/preview-proof'):
    shutil.rmtree('/home/user/preview-proof')
    log.append('removed preview-proof/')

# ---------- 2) rewrite references in every html ----------
ATTR = re.compile(r'(href|src|action)="([^"]+)"')

def shorten_ar_dots(url):
    """AR tree moved up one level: chains of 2+ leading ../ lose one."""
    m = re.match(r'^((?:\.\./)+)(.*)$', url)
    if not m:
        return url
    n = m.group(1).count('../')
    if n >= 2:
        return '../' * (n - 1) + m.group(2)
    return url

files = glob.glob('**/*.html', recursive=True)
for fname in files:
    t = open(fname, encoding='utf-8', errors='ignore').read()
    o = t
    is_ar = fname.startswith('ar/')
    # absolute / canonical first (longest match first)
    t = t.replace('ar/europe-EN', 'ar').replace('europe-EN', 'en')
    if is_ar:
        def repl(m):
            attr, url = m.group(1), m.group(2)
            if url.startswith('../'):
                return f'{attr}="{shorten_ar_dots(url)}"'
            return m.group(0)
        t = ATTR.sub(repl, t)
    if t != o:
        open(fname, 'w', encoding='utf-8').write(t)
log.append(f'rewrote references in {len(files)} html files')

# non-html text files referencing the old tree
for fname in glob.glob('assets/js/*.js') + glob.glob('*.json') + glob.glob('*.txt'):
    if os.path.basename(fname) in ('sitemap.xml', 'sitemap-products.xml'):
        continue
    t = open(fname, encoding='utf-8', errors='ignore').read()
    nt = t.replace('ar/europe-EN', 'ar').replace('europe-EN', 'en')
    if nt != t:
        open(fname, 'w', encoding='utf-8').write(nt)
        log.append('updated ' + fname)

# ---------- 3) homepage tile fix: show all 10 categories ----------
TILE_CSS = '''<style id="scor-tiles-fix">
#productGroups ul.productGroups{display:flex!important;flex-wrap:wrap!important;transform:none!important;width:auto!important;left:0!important}
#productGroups ul.productGroups>li{flex:0 0 20%!important;max-width:20%!important;float:none!important;padding:8px!important}
#productGroups .slick-list{overflow:visible!important;height:auto!important}
#productGroups .slick-track{display:flex!important;flex-wrap:wrap!important;width:auto!important;transform:none!important}
@media(max-width:900px){#productGroups ul.productGroups>li{flex-basis:33.333%!important;max-width:33.333%!important}}
@media(max-width:600px){#productGroups ul.productGroups>li{flex-basis:50%!important;max-width:50%!important}}
</style>'''
for hf in ['index.html', 'ar/index.html']:
    t = open(hf, encoding='utf-8').read()
    if 'scor-tiles-fix' not in t:
        t = t.replace('</head>', TILE_CSS + '\n</head>', 1)
        open(hf, 'w', encoding='utf-8').write(t)
        log.append('tiles fix injected in ' + hf)

print('\n'.join(log))
print('html files total:', len(files))
print('en exists:', os.path.isdir('en'), '| ar flat:', os.path.exists('ar/products.html'), '| de:', os.path.exists('de/index.html'), '| tr:', os.path.exists('tr/index.html'))

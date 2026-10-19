#!/usr/bin/env python3
"""ONE-TIME manual static card write for all 20 EN/AR category pages.

Not a generator system: this writes plain static HTML into the category pages
once, and nothing remains in the site that can regenerate or overwrite them.

Per page it:
- keeps all existing static cards (membership already verified: 154/lang)
- swaps every embedded base64 image back to the light relative path
  ../../assets/img/cat-illus/{folder}.jpg (browser caches it once)
- removes the injected counter <script data-scor-count="gen"> entirely
- keeps ONE inline <style data-scor-cards="manual-static"> block (a trimmed copy
  of the card stylesheet) placed right before </body>
Pure raw-text edits; BeautifulSoup used read-only for the final audit only.
"""
import re
from pathlib import Path

ROOT = Path('/home/user/scor1')
PAGES = []
for lang in ('en', 'ar'):
    for p in sorted((ROOT / lang / 'products').glob('*.html')):
        PAGES.append((lang, p))

def debloat() -> None:
    for lang, p in PAGES:
        folder = p.stem
        h = p.read_text(encoding='utf-8', errors='ignore')
        rel_img = f'../../assets/img/cat-illus/{folder}.jpg'
        # Every embedded photo on this page is the category image -> relative path.
        h = re.sub(r'src="data:image/[^"]+"', f'src="{rel_img}"', h)
        # Drop the injected counter script (generation remnant).
        h = re.sub(r'<script data-scor-count="gen">.*?</script>\s*', '', h, flags=re.S)
        # Make sure the static card stylesheet is linked in <head>.
        link = '<link rel="stylesheet" href="../../assets/css/scor-category-cards.css">'
        if 'scor-category-cards.css' not in h:
            h = h.replace('</head>', link + '\n</head>', 1)
        # Hold only one manual-static inline style, right before </body>,
        # so cards render even where external CSS is blocked (file viewer).
        h = re.sub(r'<style data-scor-cards[^>]*>.*?</style>\s*', '', h, flags=re.S)
        css = (ROOT / 'assets/css/scor-category-cards.css').read_text(encoding='utf-8')
        style = '<style data-scor-cards="manual-static">\n' + css + '\n</style>\n'
        if '</body>' in h:
            h = h.replace('</body>', style + '</body>', 1)
        else:
            h += style
        p.write_text(h, encoding='utf-8')
        print(f'{lang}/products/{p.name}: static cards kept, images now relative, counter script removed')

def audit() -> None:
    from bs4 import BeautifulSoup
    total = 0
    errors = []
    for lang, p in PAGES:
        h = p.read_text(encoding='utf-8', errors='ignore')
        s = BeautifulSoup(h, 'html.parser')
        cards = s.select('#products > ul > li.product')
        total += len(cards)
        if 'data-scor-count' in h:
            errors.append((str(p), 'counter script still present'))
        if 'data:image' in h and h.count('data:image') > 1:
            errors.append((str(p), f"embedded images remain: {h.count('data:image')}"))
        for c in cards:
            links = [a.get('href') for a in c.select('a[href]') if a.get('href', '').endswith('.html')]
            if len(set(links)) != 1:
                errors.append((str(p), 'card links differ', links)); continue
            if not (p.parent / links[0]).resolve().exists():
                errors.append((str(p), 'missing target', links[0]))
            img = c.select_one('img')
            if not img or not img.get('src'):
                errors.append((str(p), 'card without image', links))
    print(f'\nAUDIT: {total} static cards on {len(PAGES)} category pages; errors: {len(errors)}')
    for e in errors[:10]:
        print(' ', e)

if __name__ == '__main__':
    debloat()
    audit()

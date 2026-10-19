# -*- coding: utf-8 -*-
"""Language menu enrichment:
- Arabic language item gets the NEW Syrian flag (assets/img/flags/syria-new.svg)
- English item gets an EN badge.
- Cleanup candidates checked separately (no deletion here).
"""
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path('/home/user/scor1')
CSS_SNIPPET = """
/* language flags (localization overlay) */
#localization .scor-flag{width:26px;height:17px;object-fit:cover;vertical-align:-3px;margin-inline-end:8px;border:1px solid rgba(255,255,255,.35);border-radius:2px}
#localization .scor-en{display:inline-block;min-width:26px;text-align:center;vertical-align:-1px;margin-inline-end:8px;padding:1px 3px;border:1px solid rgba(255,255,255,.35);border-radius:2px;font-size:10px;font-weight:700;letter-spacing:.06em;color:#F5B400}
"""

touched = 0
for f in ROOT.rglob('*.html'):
    s = BeautifulSoup(f.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
    loc = s.select_one('#localization')
    if not loc:
        continue
    changed = False
    for a in loc.select('.language a, .languages a'):
        txt = a.get_text(' ', strip=True)
        if 'العربية' in txt and not a.select_one('.scor-flag'):
            img = s.new_tag('img')
            img['src'] = '/assets/img/flags/syria-new.svg'
            img['alt'] = 'Syria'
            img['class'] = 'scor-flag'
            a.insert(0, img)
            changed = True
        elif 'English' in txt and not a.select_one('.scor-en'):
            span = s.new_tag('span')
            span['class'] = 'scor-en'
            span.string = 'EN'
            a.insert(0, span)
            changed = True
    if changed:
        f.write_text(str(s), encoding='utf-8'); touched += 1
print('pages enriched with Syrian flag:', touched)

css = ROOT / 'assets/css/scor-brand.css'
c = css.read_text(encoding='utf-8')
if '.scor-flag' not in c:
    css.write_text(c.rstrip() + CSS_SNIPPET, encoding='utf-8')
print('css updated')

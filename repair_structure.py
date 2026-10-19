#!/usr/bin/env python3
"""Free the page content swallowed by the broken #localization panel.

On every category page a past edit gutted #localization: its opening chain
(<div id="localization"><div class="row"><div class="columns"><div class="container"><div>)
plus the hero text remains, and the panel's closing </div>s are missing, so the
whole <section id="page">…cards…</section> is nested inside the display:none
panel and browsers render nothing under the header.

Mechanical repair, identical on all 20 pages:
1. Replace the panel's opening chain + injected hero junk (everything from
   '<div id="localization">' up to the first '<section') with ONE well-formed
   #localization panel (language links), then let the original <section id="page">
   follow as a top-level sibling.
2. Remove one stray '</ul>' immediately before that <section> if present
   (a leftover of the injected junk).
Nothing else is touched.
"""
import re
from pathlib import Path

ROOT = Path('/home/user/scor1')

PANEL = (
 '<div id="localization"><div class="row align-center align-middle">'
 '<div class="columns small-12 medium-9"><div class="container"><div>'
 '<div class="region"><div class="name">LANGUAGE / اللغة</div>'
 '<ul class="countries row"><li class="country columns small-12 medium-3">'
 '<ul class="languages row collapse">'
 '<li class="language columns shrink"><a href="{en_url}"{en_bold}>English</a></li>'
 '<li class="language columns shrink"><a href="{ar_url}"{ar_bold}>العربية</a></li>'
 '</ul></li></ul></div></div></div></div></div></div>'
)

for lang in ('en', 'ar'):
    for p in sorted((ROOT / lang / 'products').glob('*.html')):
        folder = p.stem
        h = p.read_text(encoding='utf-8', errors='ignore')
        i = h.find('<div id="localization">')
        j = h.find('<section', i)
        assert 0 < i < j, (p, i, j)
        between = h[i:j]
        # Drop one trailing stray '</ul></div>' style junk right before <section>.
        freed_tail = ''
        m = re.search(r'</ul>\s*</div>\s*$', between)
        if m:
            freed_tail = ''  # junk removed entirely
        if lang == 'en':
            panel = PANEL.format(en_url=f'/en/products/{folder}.html', ar_url=f'/ar/products/{folder}.html', en_bold=' style="font-weight:bold;color:#F5B400;"', ar_bold='')
        else:
            panel = PANEL.format(en_url=f'/en/products/{folder}.html', ar_url=f'/ar/products/{folder}.html', en_bold='', ar_bold=' style="font-weight:bold;color:#F5B400;"')
        h = h[:i] + panel + '\n' + h[j:]
        p.write_text(h, encoding='utf-8')
        print(f'{lang}/products/{p.name}: swallowed content freed; panel rebuilt')

# -*- coding: utf-8 -*-
"""Add the company motto 'STAY AHEAD' beside the logo (header lockup) site-wide,
in the homepage hero headline area, and in the footer brand block."""
import re
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path('/home/user/scor1')

# ---------- 1) header logo lockup (raw-ish via BS but minimal: insert span after </a> of logoWrapper)
touched = 0
for f in ROOT.rglob('*.html'):
    t = f.read_text(encoding='utf-8', errors='ignore')
    original = t
    if 'STAY AHEAD' in t:
        continue
    m = re.search(r'(<a class="logoWrapper[^"]*"[^>]*>.*?</a>)', t, re.S)
    if m:
        t = t[:m.end()] + '<span class="scor-tagline" dir="ltr">STAY AHEAD</span>' + t[m.end():]
    if t != original:
        f.write_text(t, encoding='utf-8'); touched += 1
print('header tagline inserted in', touched, 'pages')

# ---------- 2) homepages: hero small line + footer brand ----------
HOME_META = {'en': 'STAY AHEAD', 'ar': 'STAY AHEAD'}
for rel, lang in (('index.html', 'en'), ('ar/index.html', 'ar')):
    p = ROOT / rel
    t = p.read_text(encoding='utf-8', errors='ignore')
    # insert tagline above the hero H1
    t = t.replace('<h1 class="bigText">',
                  '<p class="hero-tagline" dir="ltr">STAY AHEAD</p><h1 class="bigText">', 1)
    # footer brand sentence gains the motto
    t = t.replace('SCOR Heavy Equipment — Power That Builds The Future.',
                  'STAY AHEAD — SCOR Heavy Equipment. Power That Builds The Future.')
    t = t.replace('<div class="sf-brand"><p>', '<div class="sf-brand"><p>')  # no-op guard
    p.write_text(t, encoding='utf-8')

    # footer brand text on all pages: ensure motto present once (load, local)
print('homepages hero + footer refreshed')

# footer brand motto on every page (after the brand paragraph start)
count = 0
for f in ROOT.rglob('*.html'):
    t = f.read_text(encoding='utf-8', errors='ignore')
    if 'sf-brand' in t and 'Power That Builds The Future' in t and 'STAY AHEAD — SCOR' not in t:
        t = t.replace('SCOR Heavy Equipment — Power That Builds The Future.',
                      'STAY AHEAD — SCOR Heavy Equipment. Power That Builds The Future.', 1)
        f.write_text(t, encoding='utf-8'); count += 1
print('footer motto added on', count, 'pages')

# ---------- 3) shared CSS ----------
css = ROOT / 'assets/css/scor-brand.css'
c = css.read_text(encoding='utf-8')
if '.scor-tagline' not in c:
    c += """
/* STAY AHEAD — company motto next to the logo */
.scor-tagline{display:inline-block;margin-inline-start:14px;vertical-align:middle;font-weight:800;letter-spacing:.22em;font-size:15px;color:#F5B400;text-transform:uppercase;white-space:nowrap;line-height:1}
.hero-tagline{display:inline-block;margin:0 0 14px;padding:7px 16px;border:1px solid rgba(245,180,0,.85);background:rgba(21,21,21,.45);color:#F5B400;font-weight:800;letter-spacing:.3em;font-size:14px;text-transform:uppercase}
@media (max-width:640px){.scor-tagline{font-size:11px;letter-spacing:.14em;margin-inline-start:8px}.hero-tagline{font-size:11px;letter-spacing:.18em}}
"""
    css.write_text(c, encoding='utf-8')
print('DONE')

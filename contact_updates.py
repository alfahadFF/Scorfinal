# -*- coding: utf-8 -*-
"""Contact/company updates ordered 2026-10-19:
- Footer (every page): Quick contact info = Goethestr.8 / DE-77855 Achern Germany /
  Mobile +4917632230111 / Mobile +49 17634389434 / 008619821948118 wechat /
  Email fa@scor-sy.de + info@scor-sy.de ; footer Call-Us strip -> new primary number.
- Any @igtboard.com email (support/f.al/alfahad, 808+ occurrences) -> info@scor-sy.de.
- Company name IGT BOARD / IGT-BOARD UG / IGTBoard variants -> SCOR Heavy Machinery Manufacturing
  (incl. Imprint, Terms, Privacy meta+body), per "anywhere needed".
- Imprint (EN+AR): 'SCOR Heavy Machinery Manufacturing' lead line at top of the page body,
  company table aligned to the new contact block.
- About (EN+AR): first line renamed; International Director Eng. Fadi Al-Mahameed card with
  the new numbers/wechat/fa@+info@; General Manager Eng. Omar Alkhatieb (+49 1511 8316638,
  Om@scor-sy.de); new Regional Director card: Attorney Muhannad Ibrahim Yahya,
  Homs Office +963 991 552 387, Damascus Office +963 941 435 541.
Raw-text regex passes site-wide; BeautifulSoup only on the 4 targeted pages.
"""
import re
import shutil
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString

ROOT = Path('/home/user/scor1')
NEW = 'SCOR Heavy Machinery Manufacturing'

FOOTER_ADDR = (
    'Goethestr.8<br/>DE-77855 Achern Germany<br/>'
    'Mobile: <a href="tel:+4917632230111">+4917632230111</a><br/>'
    'Mobile: <a href="tel:+4917634389434">+49 17634389434</a><br/>'
    '008619821948118 wechat<br/>'
    'Email: <a href="mailto:fa@scor-sy.de">fa@scor-sy.de</a><br/>'
    '<a href="mailto:info@scor-sy.de">info@scor-sy.de</a>'
)

def set_fragment(node, html):
    node.clear()
    frag = BeautifulSoup(html, 'html.parser')
    body = frag.body if frag.body else frag
    for child in list(body.contents):
        node.append(child)

stats = {'footer_addr': 0, 'footer_strip': 0}

# ---------- 1) footer Quick-contact address block + strip number (raw text) ----------
addr_re = re.compile(r'(<address class="sf-address">).*?(</address>)', re.S)
for f in ROOT.rglob('*.html'):
    t = f.read_text(encoding='utf-8', errors='ignore')
    original = t
    t2, n = addr_re.subn(lambda m: m.group(1) + FOOTER_ADDR + m.group(2), t)
    if n:
        stats['footer_addr'] += 1
        t = t2
        # footer Call-Us strip: old mobile -> new primary (footer segment only)
        fs = t.find('<footer')
        fe = t.find('</footer>')
        if fs >= 0 and fe > fs:
            seg = t[fs:fe]
            seg = seg.replace('href="tel:+4917634389434"', 'href="tel:+4917632230111"')
            if '>+4917634389434<' in seg:
                seg = seg.replace('>+4917634389434<', '>+4917632230111<')
                stats['footer_strip'] += 1
            t = t[:fs] + seg + t[fe:]
    if t != original:
        f.write_text(t, encoding='utf-8')
print('footer address updated on', stats['footer_addr'], 'pages; Call-Us strip on', stats['footer_strip'])

# ---------- 2) global textual: emails + company name ----------
email_re = re.compile(r'[A-Za-z0-9._%+-]+@igtboard\.com', re.I)
name_res = [
    re.compile(r'IGT[\s-]*BOARD\s*UG', re.I),
    re.compile(r'IGTBoard[- ]Ug', re.I),
    re.compile(r'IGTBoard\s+Germany', re.I),
    re.compile(r'IGTBoard\s+GmbH', re.I),
    re.compile(r'IGTBoard\s+Group', re.I),
    re.compile(r'IGTBoard', re.I),
    re.compile(r'IGT[\s-]+BOARD', re.I),
    re.compile(r'IGT\s+Board', re.I),
]
touched = 0
targets = [p for p in ROOT.rglob('*') if p.is_file() and p.suffix.lower() in ('.html', '.xml', '.txt', '.json', '.css', '.js')]
targets += [Path('/home/user/scor-official-data.md'), Path('/home/user/scor-official-data.json')]
for f in targets:
    t = f.read_text(encoding='utf-8', errors='ignore')
    original = t
    t = email_re.sub('info@scor-sy.de', t)
    for r in name_res:
        t = r.sub(NEW, t)
    if t != original:
        f.write_text(t, encoding='utf-8'); touched += 1
print('global email/name rewrite touched', touched, 'files')

# ---------- 3) Imprint pages: lead line + company table ----------
for lang in ('en', 'ar'):
    p = ROOT / lang / 'services' / 'imprint.html'
    s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
    ot = s.select_one('.official-text')
    lead = s.new_tag('p'); lead['class'] = 'company-lead'; lead.string = NEW
    ot.insert(0, lead)
    table = ot.select_one('table.company-table')
    rows = {}
    for tr in table.find_all('tr'):
        th = tr.find('th')
        if th: rows[th.get_text(strip=True)] = tr
    if 'Company' in rows:
        rows['Company'].find('td').string = NEW
    if 'Address' in rows:
        rows['Address'].find('td').string = 'Goethestr.8, DE-77855 Achern Germany'
    if 'Phone' in rows:
        rows['Phone'].decompose()
    if 'Mobile' in rows:
        set_fragment(rows['Mobile'].find('td'),
            '<a href="tel:+4917632230111">+4917632230111</a><br/>'
            '<a href="tel:+4917634389434">+49 17634389434</a>')
        wc = BeautifulSoup('<tr><th>WeChat</th><td>008619821948118</td></tr>', 'html.parser')
        rows['Mobile'].insert_after(list(wc.find('tr').contents)[0].parent if False else wc.find('tr'))
    if 'Email' in rows:
        set_fragment(rows['Email'].find('td'),
            '<a href="mailto:fa@scor-sy.de">fa@scor-sy.de</a><br/>'
            '<a href="mailto:info@scor-sy.de">info@scor-sy.de</a>')
    p.write_text(str(s), encoding='utf-8')
    print(lang, 'imprint updated')

# ---------- 4) About pages: heading line + director cards ----------
FAHDI_BODY = ('Mobile: <a href="tel:+4917632230111">+4917632230111</a><br/>'
              'Mobile: <a href="tel:+4917634389434">+49 17634389434</a><br/>'
              'WeChat: 008619821948118<br/>'
              'Email: <a href="mailto:fa@scor-sy.de">fa@scor-sy.de</a><br/>'
              '<a href="mailto:info@scor-sy.de">info@scor-sy.de</a>')
OMAR_BODY = ('Mobile: <a href="tel:+4915118316638">+49 1511 8316638</a><br/>'
             'Email: <a href="mailto:Om@scor-sy.de">Om@scor-sy.de</a>')
MUHANNAD_BODY = ('Homs Office: <a href="tel:+963991552387">+963 991 552 387</a><br/>'
                 'Damascus Office: <a href="tel:+963941435541">+963 941 435 541</a>')

for lang in ('en', 'ar'):
    p = ROOT / lang / 'about-us.html'
    s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
    ot = s.select_one('.official-text')
    first = ot.find('p', recursive=False)
    if first:
        set_fragment(first, f'<strong>{NEW}</strong> — Licensed and officially registered in Germany.')
    grid = ot.select_one('.director-grid')
    cards = ot.select('.director-card')
    by_name = {}
    for c in cards:
        h = c.find('h3')
        if h: by_name[h.get_text(' ', strip=True)] = c
    for title, c in by_name.items():
        body = c.find('p')
        if 'Al-Mahameed' in title or 'المحاميد' in title:
            c.find('h3').string = 'International Director — Eng. Fadi Al-Mahameed'
            set_fragment(body, FAHDI_BODY)
        elif 'Omar' in title or 'Omar' in title:
            c.find('h3').string = 'General Manager — Eng. Omar Alkhatieb'
            set_fragment(body, OMAR_BODY)
    # new regional director card (clone Omar card for identical classes)
    donor = cards[-1]
    clone = BeautifulSoup(str(donor), 'html.parser').find('div')
    clone.find('h3').string = 'Regional Director — Attorney Muhannad Ibrahim Yahya'
    set_fragment(clone.find('p'), MUHANNAD_BODY)
    if grid is not None:
        grid.append(clone)
    else:
        donor.insert_after(clone)
    p.write_text(str(s), encoding='utf-8')
    print(lang, 'about-us updated:', len(ot.select('.director-card')), 'cards')

# ---------- 5) CSS for the Imprint lead line ----------
css = ROOT / 'assets/css/scor-footer.css'
c = css.read_text(encoding='utf-8')
if '.company-lead' not in c:
    c += f'\n.official-text .company-lead{{font-size:25px;font-weight:700;text-align:center;color:#151515;margin:4px 0 22px;padding-bottom:12px;border-bottom:3px solid #F5B400;letter-spacing:.01em}}\n'
    css.write_text(c, encoding='utf-8')
print('DONE')

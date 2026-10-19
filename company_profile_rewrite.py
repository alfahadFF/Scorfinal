# -*- coding: utf-8 -*-
"""Rewrite the company profile: the old About text described a software/education
institution (IGT profile). Replace with SCOR heavy-machinery profile EN+AR:
manufacturing, trading and rental of heavy machinery of all types, international
shipping to/from all countries, integrated logistics. Remove the software lines
from the international-logistics service pages as well."""
from bs4 import BeautifulSoup
from pathlib import Path

ROOT = Path('/home/user/scor1')

EN_BLOCK = """
<p><strong>SCOR Heavy Machinery Manufacturing</strong> — officially registered in Germany, headquartered in Achern.</p>
<h2>Who Are We?</h2>
<p>SCOR is a company specialised in the <strong>manufacturing, trading and rental of heavy machinery of all types</strong> — crawler and wheeled excavators, wheel loaders, backhoe loaders, skid steer loaders, diesel and electric forklifts, telescopic loaders, trailer concrete pumps and self-loading mixers.</p>
<h2>What We Do</h2>
<ul>
<li><strong>Manufacturing &amp; Trading</strong> — heavy machinery and construction equipment of all types, supplied to dealers and projects worldwide.</li>
<li><strong>Machine Rental</strong> — heavy equipment rental for construction and infrastructure projects, ready to work on site.</li>
<li><strong>International Shipping</strong> — shipping of machinery, equipment and spare parts from and to all countries worldwide.</li>
<li><strong>Integrated Logistics Services</strong> — freight, customs clearance, warehousing and delivery, managed end-to-end from factory gate to final job site.</li>
<li><strong>After-Sales Support</strong> — maintenance, spare parts supply and technical support that keep your machines working.</li>
</ul>
<p>From our German base in Achern, we serve customers across Europe, Türkiye and the Arab World, with sourcing operations in China. We combine German engineering standards with a wide machine range and dependable logistics, so the right machine reaches your project — on time and ready to work.</p>
<h2>Board of Directors</h2>
"""

AR_BLOCK = """
<p><strong>SCOR Heavy Machinery Manufacturing</strong> — شركة مسجّلة رسمياً في ألمانيا، مقرها في مدينة أخرن.</p>
<h2>من نحن؟</h2>
<p>SCOR شركة متخصصة في <strong>تصنيع وتجارة وتأجير المعدات الثقيلة بأنواعها</strong>: الحفارات المجنزرة والحفارات على عجلات، اللوادر، التركسات (الباكهو)، اللوادر الانزلاقية، الرافعات الشوكية الديزل والكهربائية، الرافعات التلسكوبية، مضخات الخرسانة المقطورة والخلاطات ذاتية التعبئة.</p>
<h2>ماذا نفعل؟</h2>
<ul>
<li><strong>التصنيع والتجارة</strong> — معدات ثقيلة وآليات إنشاءات بأنواعها، تورّد للوكلاء والمشاريع حول العالم.</li>
<li><strong>تأجير المعدات</strong> — تأجير المعدات الثقيلة لمشاريع البناء والبنية التحتية، جاهزة للعمل في موقعك.</li>
<li><strong>الشحن الدولي</strong> — شحن الآليات والمعدات وقطع الغيار من وإلى جميع دول العالم.</li>
<li><strong>خدمات لوجستية متكاملة</strong> — شحن وتخليص جمركي وتخزين وتوصيل، تُدار من باب المصنع حتى موقع المشروع.</li>
<li><strong>خدمة ما بعد البيع</strong> — صيانة وتوريد قطع غيار ودعم فني يبقي معداتك في الخدمة.</li>
</ul>
<p>من قاعدتنا في مدينة أخرن / ألمانيا نخدم عملاءنا في أوروبا وتركيا والعالم العربي، مع عمليات توريد من الصين. نجمع بين المعايير الهندسية الألمانية وتشكيلة واسعة من الآليات ولوجستيات موثوقة، لتصل الآلة المناسبة إلى مشروعك في وقتها جاهزة للعمل.</p>
<h2>مجلس الإدارة</h2>
"""

def set_block(ot, board_h2, block_html):
    frag = BeautifulSoup(block_html, 'html.parser')
    new_nodes = [n for n in (frag.body or frag).contents if getattr(n, 'name', None)]
    # remove everything before the Board of Directors h2 (existing heading replaced too)
    node = ot.contents[0] if ot.contents else None
    to_remove = []
    cur = ot.find(recursive=False)
    while cur is not None and cur is not board_h2:
        nxt = cur.find_next_sibling()
        to_remove.append(cur)
        cur = nxt
    if board_h2 is not None:
        bh = board_h2
        nxt = bh.find_next_sibling()
        to_remove.append(bh)
    for n in to_remove:
        if hasattr(n, 'decompose'):
            n.decompose()
    anchor = ot.find(recursive=False)
    for n in reversed(new_nodes):
        if anchor is not None:
            anchor.insert_before(BeautifulSoup(str(n), 'html.parser').body.contents[0] if BeautifulSoup(str(n), 'html.parser').body else n)
        else:
            ot.append(BeautifulSoup(str(n), 'html.parser').body.contents[0])

for lang, block in (('en', EN_BLOCK), ('ar', AR_BLOCK)):
    p = ROOT / lang / 'about-us.html'
    s = BeautifulSoup(p.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
    ot = s.select_one('.official-text')
    board = None
    for h in ot.find_all('h2'):
        if 'Board of Directors' in h.get_text() or 'مجلس الإدارة' in h.get_text():
            board = h; break
    set_block(ot, board, block)
    # meta descriptions (old education sentence)
    meta_desc = ("SCOR Heavy Machinery Manufacturing — manufacturing, trading and rental of heavy machinery of all types, "
                 "international shipping and integrated logistics services."
                 if lang == 'en' else
                 "SCOR Heavy Machinery Manufacturing — تصنيع وتجارة وتأجير المعدات الثقيلة بأنواعها، شحن دولي من وإلى جميع دول العالم وخدمات لوجستية متكاملة.")
    for m in s.find_all('meta'):
        c = m.get('content') or ''
        if 'educational' in c.lower() or 'education' in c.lower():
            m['content'] = meta_desc
    p.write_text(str(s), encoding='utf-8')
    print(lang, 'about-us profile rewritten')

# ---------- international logistics: remove software ----------
for lang in ('en', 'ar'):
    lp = ROOT / lang / 'services' / 'international-logistics.html'
    if not lp.exists(): continue
    s = BeautifulSoup(lp.read_text(encoding='utf-8', errors='ignore'), 'html.parser')
    for li in s.find_all('li'):
        if 'Digital Logistics Systems' in li.get_text():
            from bs4 import BeautifulSoup as BS
            new = BS('<li><strong>Warehousing &amp; Distribution</strong> — storage, handling and onward distribution of machinery, equipment and spare parts.</li>', 'html.parser')
            li.replace_with(new.find('li'))
    tx = str(s)
    tx = tx.replace('One partner for freight, customs, warehousing and software;',
                    'One partner for freight, customs, warehousing and delivery;')
    # 'logistics software' phrasing in intro paragraph, if present
    tx = tx.replace('logistics software', 'logistics services')
    lp.write_text(tx, encoding='utf-8')
    print(lang, 'international-logistics: software removed')
print('DONE')

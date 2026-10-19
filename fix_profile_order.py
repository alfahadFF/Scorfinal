# -*- coding: utf-8 -*-
"""Restore natural document order inside .official-text on About pages:
the profile block was inserted in reverse by the previous one-time script.
We re-insert the SAME nodes in correct order before the director grid."""
from bs4 import BeautifulSoup

for lang in ('en', 'ar'):
    p = f'/home/user/scor1/{lang}/about-us.html'
    s = BeautifulSoup(open(p, encoding='utf-8', errors='ignore'), 'html.parser')
    ot = s.select_one('.official-text')
    grid = s.select_one('.director-grid')
    # profile nodes = direct children of ot positioned before the grid, minus the Board h2
    pre = []
    for child in list(ot.contents):
        if child is grid:
            break
        if getattr(child, 'name', None):
            pre.append(child)
    board_h2 = None
    profile = []
    for n in pre:
        if n.name == 'h2' and ('Board of Directors' in n.get_text() or 'مجلس الإدارة' in n.get_text()):
            board_h2 = n
        else:
            profile.append(n)
    correct = list(reversed(profile))
    # detach all, re-append in correct order before grid
    for n in pre:
        n.extract()
    for n in correct:
        grid.insert_before(n)
    # ensure Board heading directly before grid
    if board_h2 is not None:
        grid.insert_before(board_h2)
    open(p, 'w', encoding='utf-8').write(str(s))
    heads = [c.get_text(' ', strip=True)[:40] for c in ot.find_all(recursive=False)]
    print(lang, 'order:', heads[:10])
print('DONE')

#!/usr/bin/env python3
"""Delete dead old-category cards/nav items and repair broken internal links in scor1."""
import os, re, glob
from urllib.parse import urlparse, unquote

ROOT = '/home/user/scor1'
os.chdir(ROOT)

OLD_CATS = ('soil-compactors', 'motor-graders', 'special-purpose-excavators')
ATTR = re.compile(r'(href|src)="([^"]+)"')

# ---- index of every file on disk (for suffix-candidate repair) ----
all_files = []
for dp, dn, fn in os.walk('.'):
    dn[:] = [d for d in dn if d not in ('node_modules', '.git')]
    for f in fn:
        all_files.append(os.path.normpath(os.path.join(dp, f)).replace(os.sep, '/'))

def tree_of(path):
    return 'ar/' if path.startswith('ar/') else ''

def resolve(fname, url):
    """Return local target path for a url found in fname, or None if external/anchor."""
    if not url or url.startswith(('#', 'mailto:', 'tel:', 'javascript:', 'data:')):
        return None
    p = urlparse(url)
    if p.netloc and 'scor-sy.de' not in p.netloc:
        return None
    path = unquote(p.path)
    if not path:
        return None
    if p.scheme in ('http', 'https'):
        local = path.lstrip('/')
        return local or 'index.html'
    if path.startswith('/'):
        return path.lstrip('/')
    return os.path.normpath(os.path.join(os.path.dirname(fname), path)).replace(os.sep, '/')

def is_old_cat(url):
    path = unquote(urlparse(url).path)
    base = os.path.basename(path)
    return base in tuple(c + '.html' for c in OLD_CATS) or any(('/' + c + '/') in path for c in OLD_CATS)

def is_forklift_old(url):
    base = os.path.basename(unquote(urlparse(url).path))
    if base in ('diesel-forklifts.html', 'electric-forklifts.html'):
        return False
    return base == 'forklifts.html' or '/forklifts/' in unquote(urlparse(url).path)

def dead_old(fname, url):
    if is_old_cat(url) or is_forklift_old(url):
        t = resolve(fname, url)
        return t is None or not os.path.exists(t)
    return False

def li_spans(text):
    """Balanced <li>...</li> spans (start, end)."""
    spans, stack = [], []
    for m in re.finditer(r'<li\b[^>]*>|</li\s*>', text, re.I):
        if m.group(0).lower().startswith('</'):
            if stack:
                s = stack.pop()
                spans.append((s, m.end()))
        else:
            stack.append(m.start())
    return spans

def remove_dead_li_blocks(fname, text):
    spans = li_spans(text)
    kill = []
    for s, e in sorted(spans, key=lambda x: x[1] - x[0]):
        block = text[s:e]
        hrefs = re.findall(r'href="([^"]+)"', block)
        if hrefs and all(dead_old(fname, h) for h in hrefs):
            if not any(s >= ks and e <= ke for ks, ke in kill):
                kill.append((s, e))
    for s, e in sorted(kill, reverse=True):
        text = text[:s] + text[e:]
    return text, len(kill)

def hub_rel(fname):
    hub = tree_of(fname) + 'products.html'
    d = os.path.dirname(fname)
    return os.path.relpath(hub, d).replace(os.sep, '/') if d else hub

def suffix_candidate(fname, target):
    segs = [s for s in target.split('/') if s and s != '..']
    tree = tree_of(fname)
    for n in range(min(4, len(segs)), 0, -1):
        suf = '/'.join(segs[-n:])
        cands = [f for f in all_files if f == suf or f.endswith('/' + suf)]
        if tree:
            tc = [c for c in cands if c.startswith(tree)]
            if tc: cands = tc
        else:
            tc = [c for c in cands if not c.startswith('ar/')]
            if tc: cands = tc
        if len(cands) == 1:
            return cands[0]
    return None

stats = {'li_removed': 0, 'attr_fixed': 0, 'hub_repoint': 0, 'files_touched': 0}
unfixed = {}

for fname in glob.glob('**/*.html', recursive=True):
    text = open(fname, encoding='utf-8', errors='ignore').read()
    orig = text
    text, n = remove_dead_li_blocks(fname, text)
    stats['li_removed'] += n

    def repl(m):
        attr, url = m.group(1), m.group(2)
        target = resolve(fname, url)
        if target is None or os.path.exists(target):
            return m.group(0)
        p = urlparse(url)
        frag = ('#' + p.fragment) if p.fragment else ''
        query = ('?' + p.query) if p.query else ''
        # scor-sy.de dead old-category URLs -> hub
        if p.scheme in ('http', 'https') and (is_old_cat(url) or is_forklift_old(url)):
            stats['hub_repoint'] += 1
            hub = 'https://www.scor-sy.de/' + tree_of(fname) + 'products.html'
            return f'{attr}="{hub}"'
        if is_old_cat(url) or is_forklift_old(url):
            stats['hub_repoint'] += 1
            return f'{attr}="{hub_rel(fname)}{query}{frag}"'
        if '/forklifts/' in unquote(p.path):
            dcat = tree_of(fname) + 'products/diesel-forklifts.html'
            d = os.path.dirname(fname)
            rel = os.path.relpath(dcat, d).replace(os.sep, '/') if d else dcat
            stats['hub_repoint'] += 1
            return f'{attr}="{rel}{query}{frag}"'
        cand = suffix_candidate(fname, target)
        if cand:
            stats['attr_fixed'] += 1
            if url.startswith('/'):
                return f'{attr}="/{cand}{query}{frag}"'
            d = os.path.dirname(fname)
            rel = os.path.relpath(cand, d).replace(os.sep, '/') if d else cand
            return f'{attr}="{rel}{query}{frag}"'
        if target.endswith('.html'):
            unfixed[target] = unfixed.get(target, 0) + 1
        return m.group(0)

    text = ATTR.sub(repl, text)
    if text != orig:
        open(fname, 'w', encoding='utf-8').write(text)
        stats['files_touched'] += 1

print(stats)
print('top unfixed .html targets:')
for k, v in sorted(unfixed.items(), key=lambda x: -x[1])[:15]:
    print(f'  {v:4d}x {k}')

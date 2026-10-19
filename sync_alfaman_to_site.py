#!/usr/bin/env python3
# Sync ALFAMAN generator output locations with the current static-site structure.
# ALFAMAN's StaticGenerator originally emitted:
#   EN: europe-EN/products/<cat>.html and europe-EN/products/<cat>/<model>.html
#   AR: ar/europe-EN/products/...
# Current site structure is:
#   EN: en/products/... ; AR: ar/products/...
# It also emitted EN pages into the site root. We rewrite only path strings in
# api.php and templates. Database rows, credentials and product content are untouched.

from pathlib import Path
import re

root = Path('/home/user/alfaman')
files = [root/'api.php', root/'templates/category.html', root/'templates/product.html', root/'templates/card.html']
replacements = [
    ('ar/europe-EN/products', 'ar/products'),
    ('/ar/europe-EN/', '/ar/'),
    ('europe-EN/products', 'en/products'),
    ('/europe-EN/', '/en/'),
    ('europe-EN/download', 'en/download'),
    ('europe-EN', 'en'),
]
for path in files:
    if not path.exists():
        continue
    text = path.read_text(encoding='utf-8', errors='ignore')
    original = text
    for old, new in replacements:
        text = text.replace(old, new)
    if text != original:
        path.write_text(text, encoding='utf-8')
        print(f'updated {path}')

# The generator writes EN files with prefix '' and AR files with prefix 'ar/'.
# productPage/categoryPage compute $dir as 'europe-EN/products...' (now
# 'en/products...'), so EN would incorrectly go to <site>/en/en/products.
# Add a path normalization in StaticGenerator: internal $dir stays
# 'en/products...' for canonicals/links, while the filesystem prefix for EN
# is stripped. This keeps canonical URLs /en/products/... and files at
# <site>/en/products/... / <site>/ar/products/...
api = root/'api.php'
text = api.read_text(encoding='utf-8', errors='ignore')
if 'function fsDir(' not in text:
    marker = "    private function site(): string { return rtrim($this->cfg['site'], '/'); }"
    helper = marker + "\n    private function fsDir(string $dir, string $lang): string { return $lang === 'ar' ? 'products' . substr($dir, strlen('en/products')) : $dir; }"
    if marker not in text:
        raise SystemExit('site() marker not found in api.php')
    text = text.replace(marker, helper, 1)

# productPage mkdir/file_put_contents should use filesystem dir.
text = text.replace(
    "@mkdir($this->site() . '/' . $prefix . $dir, 0777, true);\n        file_put_contents($this->site() . '/' . $prefix . $dir . '/' . $p['slug'] . '.html', $out);",
    "$fsDir = $this->fsDir($dir, $lang);\n        @mkdir($this->site() . '/' . $prefix . $fsDir, 0777, true);\n        file_put_contents($this->site() . '/' . $prefix . $fsDir . '/' . $p['slug'] . '.html', $out);",
    1,
)
# categoryPage file_put_contents needs same treatment (dir is en/products, file at prefix + products/<cat>.html).
text = text.replace(
    "file_put_contents($this->site() . '/' . $prefix . $dir . '/' . $cat['slug'] . '.html', $out);",
    "$fsDir = $this->fsDir($dir, $lang);\n        file_put_contents($this->site() . '/' . $prefix . $fsDir . '/' . $cat['slug'] . '.html', $out);",
    1,
)
api.write_text(text, encoding='utf-8')
print('normalized generator filesystem paths in api.php')

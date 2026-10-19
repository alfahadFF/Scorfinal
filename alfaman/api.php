<?php
/* =====================================================================
   ALFAMAN API — single bridge file for alfaman.html
   Talks to PostgreSQL (production) / MySQL / SQLite (local demo).
   Configure via environment variables or edit the defaults below.
   ===================================================================== */
session_start();
header('Content-Type: application/json; charset=utf-8');

$CFG = [
    'driver' => getenv('ALFAMAN_DB_DRIVER') ?: 'sqlite',   // pgsql | mysql | sqlite
    'host'   => getenv('ALFAMAN_DB_HOST') ?: '127.0.0.1',
    'port'   => getenv('ALFAMAN_DB_PORT') ?: 5432,
    'name'   => getenv('ALFAMAN_DB_NAME') ?: 'alfaman',
    'user'   => getenv('ALFAMAN_DB_USER') ?: 'alfaman',
    'pass'   => getenv('ALFAMAN_DB_PASS') ?: '',
    'sqlite' => getenv('ALFAMAN_SQLITE') ?: __DIR__ . '/data/alfaman.sqlite',
    'site'   => getenv('ALFAMAN_SITE_PATH') ?: __DIR__ . '/../scor1',
    'site_url' => 'https://www.scor-sy.de',
    'templates' => __DIR__ . '/templates',
    'generation_enabled' => false, // STOPPED 2026-10-19: static category cards are managed manually; do not generate/overwrite pages
];
/* Optional local override: config.php (see config.supabase.example.php)
   returning an array with any of the keys above — for hostings where
   environment variables cannot be set. config.php is never printed. */
if (file_exists(__DIR__ . '/config.php')) {
    $over = require __DIR__ . '/config.php';
    if (is_array($over)) $CFG = array_merge($CFG, $over);
}

function db(): PDO {
    global $CFG; static $pdo = null;
    if ($pdo) return $pdo;
    $o = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if ($CFG['driver'] === 'pgsql') {
        $pdo = new PDO("pgsql:host={$CFG['host']};port={$CFG['port']};dbname={$CFG['name']}", $CFG['user'], $CFG['pass'], $o);
    } elseif ($CFG['driver'] === 'mysql') {
        $pdo = new PDO("mysql:host={$CFG['host']};dbname={$CFG['name']};charset=utf8mb4", $CFG['user'], $CFG['pass'], $o);
    } else {
        @mkdir(dirname($CFG['sqlite']), 0777, true);
        $pdo = new PDO('sqlite:' . $CFG['sqlite'], null, null, $o);
        $pdo->exec('PRAGMA foreign_keys = ON');
        sqlite_schema($pdo);
    }
    try { $pdo->exec('ALTER TABLE products ADD COLUMN layout TEXT'); } catch (Throwable) {}
    return $pdo;
}
function sqlite_schema(PDO $p): void {
    $p->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, pass_hash TEXT, role TEXT DEFAULT 'editor', created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $p->exec("CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE, description TEXT, sort_order INTEGER DEFAULT 0)");
    $p->exec("CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER REFERENCES categories(id) ON DELETE CASCADE, title TEXT, slug TEXT, description TEXT, seo_title TEXT, seo_desc TEXT, status TEXT DEFAULT 'draft', sort_order INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(category_id, slug))");
    $p->exec("CREATE TABLE IF NOT EXISTS spec_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id) ON DELETE CASCADE, name TEXT, sort_order INTEGER DEFAULT 0)");
    $p->exec("CREATE TABLE IF NOT EXISTS spec_rows (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER REFERENCES spec_groups(id) ON DELETE CASCADE, name TEXT, value TEXT DEFAULT '', sort_order INTEGER DEFAULT 0)");
    $p->exec("CREATE TABLE IF NOT EXISTS media (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id) ON DELETE CASCADE, path TEXT, kind TEXT DEFAULT 'slider', caption TEXT, is_main INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0)");
    $p->exec("CREATE TABLE IF NOT EXISTS product_files (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id) ON DELETE CASCADE, label TEXT, path TEXT)");
    $p->exec("CREATE TABLE IF NOT EXISTS product_extras (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER REFERENCES products(id) ON DELETE CASCADE, name TEXT, description TEXT, image_path TEXT, sort_order INTEGER DEFAULT 0)");
    $p->exec("CREATE TABLE IF NOT EXISTS translations (entity TEXT, entity_id INTEGER, field TEXT, lang TEXT, value TEXT, PRIMARY KEY(entity, entity_id, field, lang))");
    $p->exec("CREATE TABLE IF NOT EXISTS activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
}
function out($data, int $code = 200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function body(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify(string $s): string { $s = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s))); return trim($s, '-') ?: 'item'; }
function sanitize_layout($raw): ?string {
    $known = ['general', 'specs', 'gallery', 'faq'];
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) return null;
    $order = array_values(array_intersect($raw['order'] ?? [], $known));
    foreach ($known as $k) if (!in_array($k, $order, true)) $order[] = $k;
    $hidden = array_values(array_intersect($raw['hidden'] ?? [], $known));
    if (count($hidden) >= count($known)) $hidden = [];
    return json_encode(['order' => $order, 'hidden' => $hidden], JSON_UNESCAPED_UNICODE);
}
function rel_path(string $fromDir, string $target): string {
    $from = $fromDir === '' ? [] : explode('/', $fromDir); $to = explode('/', $target);
    while ($from && $to && $from[0] === $to[0]) { array_shift($from); array_shift($to); }
    return str_repeat('../', count($from)) . implode('/', $to);
}
function compress_image(string $file): void {
    $info = @getimagesize($file); if (!$info) return;
    [$w, $h, $type] = $info;
    $create = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'][$type] ?? null;
    if (!$create || !($img = @$create($file))) return;
    if ($w > 1600) { $r = imagecreatetruecolor(1600, (int)round($h * 1600 / $w)); imagecopyresampled($r, $img, 0, 0, 0, 0, 1600, (int)round($h * 1600 / $w), $w, $h); imagedestroy($img); $img = $r; }
    match ($type) { IMAGETYPE_JPEG => imagejpeg($img, $file, 80), IMAGETYPE_PNG => imagepng($img, $file, 6), IMAGETYPE_WEBP => imagewebp($img, $file, 80), default => null };
    if (function_exists('imagewebp')) { $wp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file); if ($wp !== $file) imagewebp($img, $wp, 80); }
    imagedestroy($img);
}
function auth(): void { if (empty($_SESSION['uid'])) out(['error' => 'unauthorized'], 401); }
function log_act(PDO $db, string $a, string $d = ''): void { try { $db->prepare('INSERT INTO activity_log(user_id,action,details) VALUES(?,?,?)')->execute([$_SESSION['uid'] ?? null, $a, mb_substr($d, 0, 450)]); } catch (Throwable) {} }
function tr_set(PDO $db, string $en, int $id, string $f, string $l, string $v): void {
    $sql = match ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        'pgsql' => 'INSERT INTO translations(entity,entity_id,field,lang,value) VALUES(?,?,?,?,?) ON CONFLICT (entity,entity_id,field,lang) DO UPDATE SET value=EXCLUDED.value',
        'mysql' => 'INSERT INTO translations(entity,entity_id,field,lang,value) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',
        default => 'INSERT OR REPLACE INTO translations(entity,entity_id,field,lang,value) VALUES(?,?,?,?,?)',
    };
    $db->prepare($sql)->execute([$en, $id, $f, $l, $v]);
}
function tr_get(PDO $db, string $en, int $id, string $f, string $l): string {
    $s = $db->prepare('SELECT value FROM translations WHERE entity=? AND entity_id=? AND field=? AND lang=?');
    $s->execute([$en, $id, $f, $l]); return (string)($s->fetchColumn() ?: '');
}

$action = $_GET['action'] ?? '';
$db = db();

/* ---------- access control ---------- */
/* No accounts, no login screen: the admin page URL is the secret. As a
   second lock, every call must carry the shared key embedded in
   alfaman.html (same word in both files). If the page link ever leaks,
   change this key in api.php + alfaman.html, and rename the page. */
if (($_GET['k'] ?? '') !== '430acb23468ca83843dcc5eadee2d458') out(['error' => 'forbidden'], 403);

/* ---------- reads ---------- */
if ($action === 'bootstrap') {
    $cats = $db->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cats as &$c) $c['name_ar'] = tr_get($db, 'category', (int)$c['id'], 'name', 'ar');
    $prods = $db->query('SELECT p.id,p.title,p.slug,p.status,p.category_id,c.name AS cat FROM products p JOIN categories c ON c.id=p.category_id ORDER BY c.sort_order,c.name,p.sort_order,p.title')->fetchAll(PDO::FETCH_ASSOC);
    out(['categories' => $cats, 'products' => $prods]);
}
if ($action === 'product') {
    $id = (int)($_GET['id'] ?? 0);
    $s = $db->prepare('SELECT * FROM products WHERE id=?'); $s->execute([$id]);
    $p = $s->fetch(PDO::FETCH_ASSOC); if (!$p) out(['error' => 'not found'], 404);
    $p['description_ar'] = tr_get($db, 'product', $id, 'description', 'ar');
    $gs = $db->prepare('SELECT * FROM spec_groups WHERE product_id=? ORDER BY sort_order,id'); $gs->execute([$id]);
    $p['groups'] = $gs->fetchAll(PDO::FETCH_ASSOC);
    $rs = $db->prepare('SELECT * FROM spec_rows WHERE group_id=? ORDER BY sort_order,id');
    foreach ($p['groups'] as &$g) { $rs->execute([$g['id']]); $g['rows'] = $rs->fetchAll(PDO::FETCH_ASSOC); }
    $ms = $db->prepare('SELECT * FROM media WHERE product_id=? ORDER BY kind,is_main DESC,sort_order,id'); $ms->execute([$id]);
    $p['media'] = $ms->fetchAll(PDO::FETCH_ASSOC);
    $fs = $db->prepare('SELECT * FROM product_files WHERE product_id=?'); $fs->execute([$id]);
    $p['files'] = $fs->fetchAll(PDO::FETCH_ASSOC);
    out($p);
}

/* ---------- writes ---------- */
if ($action === 'save_category') {
    $b = body(); $slug = slugify($b['slug'] ?: $b['name']);
    $sql = match ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        'mysql' => 'INSERT IGNORE INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?)',
        'pgsql' => 'INSERT INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?) ON CONFLICT (slug) DO NOTHING',
        default => 'INSERT OR IGNORE INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?)',
    };
    $db->prepare($sql)->execute([trim($b['name']), $slug, trim($b['description'] ?? ''), (int)($b['sort_order'] ?? 0)]);
    $cid = (int)$db->query('SELECT id FROM categories WHERE slug=' . $db->quote($slug))->fetchColumn();
    if (!empty($b['name_ar'])) tr_set($db, 'category', $cid, 'name', 'ar', trim($b['name_ar']));
    log_act($db, 'save_category', $b['name']);
    out(['ok' => true, 'id' => $cid]);
}
if ($action === 'delete_category') { $db->prepare('DELETE FROM categories WHERE id=?')->execute([(int)body()['id']]); out(['ok' => true]); }

if ($action === 'save_product') {
    $b = body();
    $title = trim($b['title']); $slug = slugify($b['slug'] ?: $title);
    $vals = [(int)$b['category_id'], $title, $slug, trim($b['description'] ?? ''), trim($b['seo_title'] ?? '') ?: null, trim($b['seo_desc'] ?? '') ?: null, ($b['status'] ?? '') === 'published' ? 'published' : 'draft', (int)($b['sort_order'] ?? 0)];
    $layout = sanitize_layout($b['layout'] ?? null);
    if (!empty($b['id'])) { $db->prepare('UPDATE products SET category_id=?,title=?,slug=?,description=?,seo_title=?,seo_desc=?,status=?,sort_order=?,layout=? WHERE id=?')->execute([...$vals, $layout, (int)$b['id']]); $id = (int)$b['id']; }
    else { $db->prepare('INSERT INTO products(category_id,title,slug,description,seo_title,seo_desc,status,sort_order,layout) VALUES(?,?,?,?,?,?,?,?,?)')->execute([...$vals, $layout]); $id = (int)$db->lastInsertId(); }
    tr_set($db, 'product', $id, 'description', 'ar', trim($b['description_ar'] ?? ''));
    $db->prepare('DELETE FROM spec_groups WHERE product_id=?')->execute([$id]);
    $gi = 0;
    foreach (($b['groups'] ?? []) as $g) {
        if (trim($g['name'] ?? '') === '') continue;
        $db->prepare('INSERT INTO spec_groups(product_id,name,sort_order) VALUES(?,?,?)')->execute([$id, trim($g['name']), $gi++]);
        $gid = (int)$db->lastInsertId(); $ri = 0;
        foreach (($g['rows'] ?? []) as $r) { if (trim($r['name'] ?? '') !== '') $db->prepare('INSERT INTO spec_rows(group_id,name,value,sort_order) VALUES(?,?,?,?)')->execute([$gid, trim($r['name']), trim($r['value'] ?? ''), $ri++]); }
    }
    log_act($db, 'save_product', $title);
    // Generation stopped: saving never writes static pages.
    out(['ok' => true, 'id' => $id]);
}
if ($action === 'delete_product') { $db->prepare('DELETE FROM products WHERE id=?')->execute([(int)body()['id']]); out(['ok' => true]); }

if ($action === 'upload') {
    $pid = (int)($_POST['product_id'] ?? 0); $kind = ($_POST['kind'] ?? 'slider') === 'extra' ? 'extra' : 'slider';
    $s = $db->prepare('SELECT slug FROM products WHERE id=?'); $s->execute([$pid]); $slug = $s->fetchColumn();
    if (!$slug || empty($_FILES['file'])) out(['error' => 'bad upload'], 400);
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $isPdf = $ext === 'pdf' && ($_POST['kind'] ?? '') === 'pdf';
    if ($isPdf) {
        $dirRel = 'en/download/products/leaflets/' . $slug; $dir = $CFG['site'] . '/' . $dirRel; @mkdir($dir, 0777, true);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($_FILES['file']['name']));
        if (!move_uploaded_file($_FILES['file']['tmp_name'], "$dir/$name")) out(['error' => 'move failed'], 500);
        $db->prepare('INSERT INTO product_files(product_id,label,path) VALUES(?,?,?)')->execute([$pid, $_POST['label'] ?: 'Leaflet (PDF)', "$dirRel/$name"]);
        out(['ok' => true, 'path' => "$dirRel/$name"]);
    }
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) out(['error' => 'image type not allowed'], 400);
    $dirRel = 'assets/img/scor-products/' . $slug; $dir = $CFG['site'] . '/' . $dirRel; @mkdir($dir, 0777, true);
    $n = 1; while (file_exists("$dir/$n.$ext")) $n++;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], "$dir/$n.$ext")) out(['error' => 'move failed'], 500);
    compress_image("$dir/$n.$ext");
    $max = (int)$db->query("SELECT COALESCE(MAX(sort_order),-1) FROM media WHERE product_id=$pid AND kind='$kind'")->fetchColumn();
    $db->prepare('INSERT INTO media(product_id,path,kind,sort_order) VALUES(?,?,?,?)')->execute([$pid, "$dirRel/$n.$ext", $kind, $max + 1]);
    out(['ok' => true, 'path' => "$dirRel/$n.$ext"]);
}
if ($action === 'media_update') {
    $b = body();
    if (isset($b['caption'])) $db->prepare('UPDATE media SET caption=? WHERE id=?')->execute([trim($b['caption']) ?: null, (int)$b['id']]);
    if (!empty($b['set_main'])) { $s = $db->prepare('SELECT product_id FROM media WHERE id=?'); $s->execute([(int)$b['id']]); $pid = (int)$s->fetchColumn(); $db->prepare("UPDATE media SET is_main=0 WHERE product_id=? AND kind='slider'")->execute([$pid]); $db->prepare('UPDATE media SET is_main=1 WHERE id=?')->execute([(int)$b['id']]); }
    out(['ok' => true]);
}
if ($action === 'delete_media') { $db->prepare('DELETE FROM media WHERE id=?')->execute([(int)body()['id']]); out(['ok' => true]); }
if ($action === 'delete_file') { $db->prepare('DELETE FROM product_files WHERE id=?')->execute([(int)body()['id']]); out(['ok' => true]); }

/* ---------- Excel import (browser parses xlsx with SheetJS, sends JSON) ---------- */
if ($action === 'import_excel') {
    $b = body();
    $catBySlug = []; $prodBySlug = [];
    foreach (($b['categories'] ?? []) as $c) {
        if (empty($c['slug'])) continue;
        $sql = match ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => 'INSERT IGNORE INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?)',
            'pgsql' => 'INSERT INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?) ON CONFLICT (slug) DO NOTHING',
            default => 'INSERT OR IGNORE INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?)',
        };
        $db->prepare($sql)->execute([$c['name'] ?? $c['slug'], $c['slug'], $c['description'] ?? '', (int)($c['sort_order'] ?? 0)]);
    }
    foreach ($db->query('SELECT id,slug FROM categories')->fetchAll(PDO::FETCH_ASSOC) as $c) $catBySlug[$c['slug']] = (int)$c['id'];
    $nP = 0;
    foreach (($b['products'] ?? []) as $i => $p) {
        $cid = $catBySlug[$p['category_slug'] ?? ''] ?? null; if (!$cid || empty($p['title'])) continue;
        $slug = slugify($p['slug'] ?: $p['title']);
        $psql = match ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => 'INSERT IGNORE INTO products(category_id,title,slug,description,status,sort_order) VALUES(?,?,?,?,?,?)',
            'pgsql' => 'INSERT INTO products(category_id,title,slug,description,status,sort_order) VALUES(?,?,?,?,?,?) ON CONFLICT (category_id, slug) DO NOTHING',
            default => 'INSERT OR IGNORE INTO products(category_id,title,slug,description,status,sort_order) VALUES(?,?,?,?,?,?)',
        };
        $db->prepare($psql)->execute([$cid, $p['title'], $slug, $p['description_en'] ?? '', ($p['status'] ?? 'draft') === 'published' ? 'published' : 'draft', (int)($p['sort_order'] ?? $i)]);
        $pid = (int)$db->query('SELECT id FROM products WHERE category_id=' . $cid . ' AND slug=' . $db->quote($slug))->fetchColumn();
        $prodBySlug[$slug] = $pid;
        if (!empty($p['description_ar'])) tr_set($db, 'product', $pid, 'description', 'ar', $p['description_ar']);
        $nP++;
    }
    $groups = [];
    foreach (($b['specs'] ?? []) as $r) {
        $pid = $prodBySlug[$r['model_slug'] ?? ''] ?? null; if (!$pid || empty($r['name'])) continue;
        $key = $pid . '|' . $r['group'];
        if (!isset($groups[$key])) {
            $db->prepare('DELETE FROM spec_groups WHERE product_id=? AND name=?')->execute([$pid, $r['group'] ?: 'General']);
            $db->prepare('INSERT INTO spec_groups(product_id,name,sort_order) VALUES(?,?,?)')->execute([$pid, $r['group'] ?: 'General', count($groups)]);
            $groups[$key] = (int)$db->lastInsertId(); $groups[$key . '#n'] = 0;
        }
        $db->prepare('INSERT INTO spec_rows(group_id,name,value,sort_order) VALUES(?,?,?,?)')->execute([$groups[$key], $r['name'], $r['value'] ?? '', $groups[$key . '#n']++]);
    }
    foreach (($b['media'] ?? []) as $m) {
        $pid = $prodBySlug[$m['model_slug'] ?? ''] ?? null; if (!$pid || empty($m['path'])) continue;
        $db->prepare('INSERT INTO media(product_id,path,kind,caption,is_main,sort_order) VALUES(?,?,?,?,?,?)')
           ->execute([$pid, $m['path'], ($m['kind'] ?? 'slider') === 'extra' ? 'extra' : 'slider', $m['caption'] ?? null, (int)($m['is_main'] ?? 0), 0]);
    }
    log_act($db, 'import_excel', "$nP products");
    out(['ok' => true, 'products' => $nP]);
}

/* ---------- publishing (static generation) ---------- */
if ($action === 'publish_one') { out(['ok' => true, 'generation_disabled' => true, 'message' => 'Static generation is stopped; cards are managed manually.']); }
if ($action === 'publish_all') { out(['ok' => true, 'generation_disabled' => true, 'count' => 0, 'message' => 'Static generation is stopped; cards are managed manually.']); }

out(['error' => 'unknown action'], 404);

/* ================= static generator (same templates as the site) ================= */
function product_groups(PDO $db, int $pid): array {
    $s = $db->prepare('SELECT * FROM spec_groups WHERE product_id=? ORDER BY sort_order,id'); $s->execute([$pid]);
    $g = $s->fetchAll(PDO::FETCH_ASSOC);
    $rs = $db->prepare('SELECT * FROM spec_rows WHERE group_id=? ORDER BY sort_order,id');
    foreach ($g as &$x) { $rs->execute([$x['id']]); $x['rows'] = $rs->fetchAll(PDO::FETCH_ASSOC); }
    return $g;
}
function product_media(PDO $db, int $pid, string $kind): array {
    $s = $db->prepare('SELECT * FROM media WHERE product_id=? AND kind=? ORDER BY is_main DESC,sort_order,id'); $s->execute([$pid, $kind]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function load_product(PDO $db, int $id): ?array {
    $s = $db->prepare('SELECT * FROM products WHERE id=?'); $s->execute([$id]);
    $p = $s->fetch(PDO::FETCH_ASSOC); if (!$p) return null;
    $p['groups'] = product_groups($db, $id);
    $p['slider'] = product_media($db, $id, 'slider');
    $p['extra'] = product_media($db, $id, 'extra');
    $p['desc_ar'] = tr_get($db, 'product', $id, 'description', 'ar');
    return $p;
}
function cat_products(PDO $db, int $cid): array {
    $s = $db->prepare("SELECT * FROM products WHERE category_id=? AND status='published' ORDER BY sort_order,title"); $s->execute([$cid]);
    $out = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $p['groups'] = product_groups($db, (int)$p['id']);
        $p['slider'] = product_media($db, (int)$p['id'], 'slider');
        $p['extra'] = product_media($db, (int)$p['id'], 'extra');
        $p['desc_ar'] = tr_get($db, 'product', (int)$p['id'], 'description', 'ar');
        $out[] = $p;
    }
    return $out;
}
function publish_product(PDO $db, int $pid): void {
    if (empty($GLOBALS['CFG']['generation_enabled'])) return; // generation stopped
    $p = load_product($db, $pid); if (!$p) return;
    $s = $db->prepare('SELECT * FROM categories WHERE id=?'); $s->execute([$p['category_id']]);
    $cat = $s->fetch(PDO::FETCH_ASSOC);
    $gen = new StaticGenerator($GLOBALS['CFG'], $db);
    foreach (['en', 'ar'] as $lang) { $gen->productPage($p, $cat, $lang); $gen->categoryPage($cat, cat_products($db, (int)$cat['id']), $lang); }
    log_act($db, 'publish_product', $p['title']);
}
function publish_all(PDO $db): int {
    $cfg = $GLOBALS['CFG'];
    if (empty($cfg['generation_enabled'])) return 0; // generation stopped
    $gen = new StaticGenerator($cfg, $db);
    $count = 0; $urls = [$cfg['site_url'] . '/', $cfg['site_url'] . '/ar/'];
    $existing = @file_get_contents(rtrim($cfg['site'], '/') . '/sitemap.xml');
    $old = [];
    if ($existing && preg_match_all('#<loc>(https://www\.scor-sy\.de/[^<]*)</loc>#', $existing, $m)) $old = $m[1];
    foreach ($db->query('SELECT * FROM categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC) as $cat) {
        $products = cat_products($db, (int)$cat['id']);
        if (!$products) continue;
        foreach (['en', 'ar'] as $lang) $gen->categoryPage($cat, $products, $lang);
        foreach ($products as $p) {
            foreach (['en', 'ar'] as $lang) $gen->productPage($p, $cat, $lang);
            $count++;
        }
    }
    foreach ($old as $u) $urls[] = $u;
    $urls = array_values(array_unique($urls));
    $gen->sitemap($urls);
    log_act($db, 'publish_all', "$count products");
    return $count;
}

class StaticGenerator {
    private ?array $i18n = null;
    public function __construct(private array $cfg, ?PDO $db = null) { $this->db = $db; }
    private ?PDO $db = null; private array $rowTr = [];
    /* Arabic override for a spec row's name/value (translations table), if any. */
    private function trRow(int $rowId, string $field, string $lang): string {
        if ($lang !== 'ar' || !$this->db || $rowId <= 0) return '';
        $k = $rowId . '|' . $field;
        if (!array_key_exists($k, $this->rowTr)) $this->rowTr[$k] = tr_get($this->db, 'spec_row', $rowId, $field, 'ar');
        return $this->rowTr[$k];
    }
    private function specName(array $r, string $lang): string {
        $t = $this->trRow((int)($r['id'] ?? 0), 'name', $lang);
        return $t !== '' ? $t : $this->trSpec('rows', $r['name'], $lang);
    }
    private function specValue(array $r, string $lang): string {
        $t = $this->trRow((int)($r['id'] ?? 0), 'value', $lang);
        return $t !== '' ? $t : (string)$r['value'];
    }
    private function site(): string { return rtrim($this->cfg['site'], '/'); }
    private function fsDir(string $dir, string $lang): string { return $lang === 'ar' ? 'products' . substr($dir, strlen('en/products')) : $dir; }
    private function i18n(): array { return $this->i18n ??= require __DIR__ . '/i18n.php'; }
    private function catName(array $cat, string $lang, PDO $db = null): string {
        if ($lang === 'en') return $cat['name'];
        if ($db) { $v = tr_get($db, 'category', (int)$cat['id'], 'name', 'ar'); if ($v) return $v; }
        return $this->i18n()['cats'][$cat['slug']]['ar'] ?? $cat['name'];
    }
    private function desc(array $p, array $cat, string $lang): string {
        $d = $lang === 'ar' ? trim((string)($p['desc_ar'] ?? '')) : trim((string)$p['description']);
        if (mb_strlen($d) >= 40) return $d;
        if ($lang === 'ar') return strtr($this->i18n()['desc_tpl']['ar'], ['{title}' => $p['title'], '{cat}' => $this->i18n()['cats'][$cat['slug']]['ar'] ?? $cat['name']]);
        return "The SCOR {$p['title']} belongs to our {$cat['name']} range, engineered for reliable performance, durability and low operating costs on demanding job sites.";
    }
    private function trSpec(string $kind, string $name, string $lang): string {
        return $lang === 'en' ? $name : ($this->i18n()[$kind]['ar'][$name] ?? $name);
    }
    private function localize(string $html, string $lang, string $canonPath): string {
        if ($lang === 'en') return $html;
        $dict = $this->i18n()['ui']['ar'];
        uksort($dict, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $html = strtr($html, $dict);
        /* root-relative asset refs in the Arabic tree (mirror rule: every
           non-HTML relative ref resolves against the EN page location) */
        $baseDir = dirname($canonPath);
        $html = preg_replace_callback('#(href|src)="([^"]+)"#', function ($m) use ($baseDir) {
            $ref = $m[2];
            if ($ref === '' || $ref[0] === '/' || $ref[0] === '#' || preg_match('#^(https?:|mailto:|tel:|data:)#', $ref)) return $m[0];
            $q = ''; $p = $ref;
            if (($qi = strpos($ref, '?')) !== false) { $p = substr($ref, 0, $qi); $q = substr($ref, $qi); }
            if (str_ends_with($p, '.html')) return $m[0];
            $parts = [];
            foreach (explode('/', $baseDir . '/' . $p) as $seg) {
                if ($seg === '' || $seg === '.') continue;
                if ($seg === '..') { array_pop($parts); continue; }
                $parts[] = $seg;
            }
            return $m[1] . '="/' . implode('/', $parts) . $q . '"';
        }, $html);
        $html = preg_replace('/<html[^>]*>/', '<html lang="ar" dir="rtl">', $html, 1);
        $html = str_replace('</head>', '<link rel="stylesheet" href="/assets/css/scor-rtl.css">' . "\n</head>", $html);
        $html = str_replace($this->cfg['site_url'] . '/' . $canonPath, $this->cfg['site_url'] . '/ar/' . $canonPath, $html);
        return $html;
    }
    public function productPage(array $p, array $cat, string $lang = 'en'): void {
        $tpl = file_get_contents($this->cfg['templates'] . '/product.html');
        $dir = 'en/products/' . $cat['slug'];
        $catName = $this->catName($cat, $lang);
        $desc = $this->desc($p, $cat, $lang);
        $slider = $p['slider'] ?? []; $extra = $p['extra'] ?? [];
        $slides = ''; $thumbs = '';
        foreach ($slider as $i => $m) {
            $src = rel_path($dir, $m['path']);
            $slides .= '<img class="slide' . ($i === 0 ? ' active' : '') . '" src="' . e($src) . '" alt="' . e($p['title']) . '">';
            $thumbs .= '<button type="button" class="' . ($i === 0 ? 'active' : '') . '"><img src="' . e($src) . '" alt="' . e($p['title']) . ' ' . ($i + 1) . '"></button>';
        }
        if ($slides === '') { $fb = rel_path($dir, 'assets/img/companies/scor-logo-dark.png'); $slides = '<img class="slide active" src="' . e($fb) . '" alt="' . e($p['title']) . '">'; }
        $specs = '';
        foreach (($p['groups'] ?? []) as $g) {
            $rows = '';
            foreach ($g['rows'] as $r) $rows .= '<li class="spec columns small-12 medium-12 large-6"><div><div class="row"><div class="columns"><div class="key">' . e($this->specName($r, $lang)) . '</div></div><div class="columns shrink"><div class="value">' . e($this->specValue($r, $lang)) . '</div></div></div></div></li>';
            $specs .= '<li class="columns small-12 medium-12"><div class="specGroup"><ul class="specs row"><li class="spec columns small-12 medium-12"><div><div class="row"><div class="columns small-12"><div class="key category"><b>' . e(mb_strtoupper($this->trSpec('groups', $g['name'], $lang), 'UTF-8')) . '</b></div></div></div></div></li>' . $rows . '</ul></div></li>';
        }
        if ($specs === '') $specs = '<li class="columns small-12"><div class="specGroup"><p>info@scor-sy.de</p></div></li>';
        $views = '';
        foreach (array_merge($slider, $extra) as $i => $m) {
            $cap = $m['caption'] ?: ($p['title'] . ' — ' . ($lang === 'ar' ? 'صورة' : 'View') . ' ' . ($i + 1));
            $views .= '<figure><img src="' . e(rel_path($dir, $m['path'])) . '" alt="' . e($cap) . '" loading="lazy"><figcaption>' . e($cap) . '</figcaption></figure>';
        }
        if ($views === '') $views = '<p>—</p>';
        $canonPath = $dir . '/' . $p['slug'];
                $faqHtml = '<div class="detail"><p><b>How can I get spare parts and service for this machine?</b><br/>SCOR provides genuine parts supply, equipment care and operator training. Contact us at <a href="mailto:info@scor-sy.de">info@scor-sy.de</a> or +49 176 34389434 (Mon–Sat: 10–6 Pm).</p><p><b>How can I request a price offer?</b><br/>Use the “Request Offer” button on this page or email <a href="mailto:info@scor-sy.de">info@scor-sy.de</a> with the model name.</p></div>';
        $tab = function (string $title, string $content, bool $transition): string {
            $tr = $transition ? ' transition' : '';
            return '<li class="tab active">' . "\n" . '<div class="header row">' . "\n" . '<div class="columns"><h3>' . $title . '</h3></div>' . "\n"
                . '<div class="columns shrink">' . "\n" . '<div class="action">' . "\n"
                . '<span class="on icon-plus-circled' . $tr . '"></span>' . "\n" . '<span class="off icon-minus-circled' . $tr . '"></span>' . "\n"
                . '</div>' . "\n" . '</div>' . "\n" . '</div>' . "\n" . '<div class="content">' . $content . '</div>' . "\n" . '</li>';
        };
        $sec = [
            'general' => $tab('General Information', '<div class="detail"><p>' . e($desc) . '</p></div>', true),
            'specs' => $tab('Technical Specs', '<div><ul class="specGroups row collapse">' . $specs . '</ul></div>', false),
            'gallery' => $tab('Gallery', '<div class="scor-more-views">' . $views . '</div>', false),
            'faq' => $tab('FAQ', $faqHtml, false),
        ];
        $layout = is_string($p['layout'] ?? null) ? json_decode($p['layout'], true) : ($p['layout'] ?? null);
        $order = ['general', 'specs', 'gallery', 'faq']; $hidden = [];
        if (is_array($layout)) {
            $o = array_values(array_intersect($layout['order'] ?? [], $order));
            foreach ($order as $k) if (!in_array($k, $o, true)) $o[] = $k;
            $order = $o; $hidden = array_values(array_intersect($layout['hidden'] ?? [], $order));
            if (count($hidden) >= 4) $hidden = [];
        }
        $sectionsHtml = '';
        foreach ($order as $k) { if (in_array($k, $hidden, true)) continue; $sectionsHtml .= $sec[$k] . "\n"; }
        $out = strtr($tpl, [
            '{{TITLE}}' => e($p['title']), '{{SLUG}}' => e($p['slug']), '{{CAT_SLUG}}' => e($cat['slug']),
            '{{CAT_NAME_UPPER}}' => e(mb_strtoupper($catName, 'UTF-8')),
            '{{META_DESC}}' => e($p['seo_desc'] ?: mb_substr($desc, 0, 157)),
            '{{CANONICAL}}' => $this->cfg['site_url'] . '/' . $canonPath,
            '{{DESC}}' => e($desc), '{{SLIDES}}' => $slides, '{{THUMBS}}' => $thumbs,
            '{{COUNTER}}' => '1 / ' . max(1, count($slider)), '{{SPECS_HTML}}' => $specs, '{{EXTRA_VIEWS}}' => $views, '{{SECTIONS_HTML}}' => $sectionsHtml,
        ]);
        if (empty($slider)) {
            /* no photos yet: template's {{SLUG}}/N.jpg guesses would 404 */
            $out = str_replace('assets/img/scor-products/' . $p['slug'] . '/1.jpg', 'assets/img/card_og.png', $out);
            $out = preg_replace('#\s*"https://www\.scor-sy\.de/assets/img/scor-products/' . preg_quote($p['slug'], '#') . '/[234]\.jpg",?#', '', $out);
        }
        $out = $this->localize($out, $lang, $canonPath);
        $prefix = $lang === 'en' ? '' : $lang . '/';
        $fsDir = $this->fsDir($dir, $lang);
        @mkdir($this->site() . '/' . $prefix . $fsDir, 0777, true);
        file_put_contents($this->site() . '/' . $prefix . $fsDir . '/' . $p['slug'] . '.html', $out);
    }
    public function categoryPage(array $cat, array $products, string $lang = 'en'): void {
        $tpl = file_get_contents($this->cfg['templates'] . '/category.html');
        $cardTpl = file_get_contents($this->cfg['templates'] . '/card.html');
        $dir = 'en/products';
        $catName = $this->catName($cat, $lang);
        $catDesc = $lang === 'ar' ? ($this->i18n()['cat_desc'][$cat['slug']]['ar'] ?? $catName) : ($cat['description'] ?: ('SCOR ' . $cat['name'] . ' built for power, reliability and efficiency.'));
        $cards = ''; $firstImg = '../../assets/img/companies/scor-logo-dark.png';
        foreach ($products as $p) {
            $img = ($p['slider'][0]['path'] ?? null);
            if ($img && str_contains($firstImg, 'companies')) $firstImg = '../../' . $img;
            $rows = ''; $count = 0;
            foreach (($p['groups'] ?? []) as $g) {
                foreach ($g['rows'] as $r) {
                    if ($r['value'] === '' || $r['value'] === '—' || $count >= 4) continue;
                    $rows .= '<li class="spec columns small-12"><div class="row"><div class="columns specName">' . e($this->specName($r, $lang)) . '</div><div class="columns shrink specValue">' . e($this->specValue($r, $lang)) . '</div></div></li>';
                    $count++;
                }
                if ($count >= 4) break;
            }
            $cards .= strtr($cardTpl, ['{{LINK}}' => $cat['slug'] . '/' . $p['slug'] . '.html', '{{IMG}}' => $img ? e(rel_path($dir, $img)) : '', '{{TITLE}}' => e($p['title']), '{{DESC_SHORT}}' => e(mb_substr($this->desc($p, $cat, $lang), 0, 130)), '{{SPEC_ROWS}}' => $rows]);
        }
        $canonPath = 'en/products/' . $cat['slug'];
        $out = strtr($tpl, ['{{CAT_NAME}}' => e($catName), '{{CAT_NAME_UPPER}}' => e(mb_strtoupper($catName, 'UTF-8')), '{{CAT_SLUG}}' => e($cat['slug']), '{{CAT_DESC}}' => e($catDesc), '{{CAT_FIRST_IMG}}' => e($firstImg), '{{META_DESC}}' => e($catDesc), '{{CANONICAL}}' => $this->cfg['site_url'] . '/' . $canonPath, '{{PRODUCT_CARDS}}' => $cards]);
        $out = $this->localize($out, $lang, $canonPath);
        $prefix = $lang === 'en' ? '' : $lang . '/';
        $fsDir = $this->fsDir($dir, $lang);
        file_put_contents($this->site() . '/' . $prefix . $fsDir . '/' . $cat['slug'] . '.html', $out);
    }
    public function sitemap(array $urls): void {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($urls as $u) { $prio = str_contains($u, '/products/') ? '0.8' : '0.6'; $xml[] = "  <url><loc>" . e($u) . "</loc><lastmod>" . date('Y-m-d') . "</lastmod><priority>$prio</priority></url>"; }
        $xml[] = '</urlset>';
        file_put_contents($this->site() . '/sitemap.xml', implode("\n", $xml) . "\n");
    }
}

<?php
/* Seed the 16 Sheets products (sheets-data.json) into the configured DB.
   CLI: php seed-sheets.php — works with pgsql (Supabase) and mysql (one.com). */
/* One-time seed for the 16 Sheets products. On a web server it demands
   ?key=scor-seed-4815 (CLI runs freely). DELETE this file after use. */
if (PHP_SAPI !== 'cli' && (($_GET['key'] ?? '') !== 'scor-seed-4815')) { http_response_code(403); exit('forbidden'); }
$cfg = require __DIR__ . '/config.php';
$dsn = match ($cfg['driver']) {
    'pgsql' => "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']}",
    'mysql' => "mysql:host={$cfg['host']};port=" . ($cfg['port'] ?? 3306) . ";dbname={$cfg['name']};charset=utf8mb4",
    default => 'sqlite:' . ($cfg['sqlite'] ?? __DIR__ . '/data/alfaman.sqlite'),
};
$pdo = new PDO($dsn, $cfg['user'] ?? null, $cfg['pass'] ?? null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$data = json_decode(file_get_contents(__DIR__ . '/sheets-data.json'), true);
$trSql = match ($cfg['driver']) {
    'mysql' => "INSERT INTO translations(entity,entity_id,field,lang,value) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)",
    'pgsql' => "INSERT INTO translations(entity,entity_id,field,lang,value) VALUES(?,?,?,?,?) ON CONFLICT (entity,entity_id,field,lang) DO UPDATE SET value=EXCLUDED.value",
    default => "INSERT OR REPLACE INTO translations(entity,entity_id,field,lang,value) VALUES(?,?,?,?,?)",
};
$tr = $pdo->prepare($trSql);
$cats = [];
foreach (['skid-steer-loaders'=>['Skid Steer Loaders','لوادر انزلاقية (سكيد ستير)'], 'trailer-concrete-pumps'=>['Trailer Concrete Pumps','مضخات خرسانة مقطورة']] as $slug=>[$name,$ar]) {
    $catSql = match ($cfg['driver']) {
        'mysql' => "INSERT IGNORE INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?)",
        'pgsql' => "INSERT INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?) ON CONFLICT (slug) DO NOTHING",
        default => "INSERT OR IGNORE INTO categories(name,slug,description,sort_order) VALUES(?,?,?,?)",
    };
    $pdo->prepare($catSql)->execute([$name,$slug,'',$slug==='skid-steer-loaders'?5:6]);
    $id = (int)$pdo->query("SELECT id FROM categories WHERE slug=".$pdo->quote($slug))->fetchColumn();
    $tr->execute(['category',$id,'name','ar',$ar]); $cats[$slug]=$id;
}
$payloadFile = __DIR__ . '/import-payload.json';
$payload = file_exists($payloadFile) ? json_decode(file_get_contents($payloadFile), true) : ['media' => []];
$sy145media = array_values(array_filter($payload['media'], fn($m)=>($m['model_slug']??'')==='sy145'));
$report = [];
foreach ($data as $p) {
    $cid = $cats[$p['category']];
    $ex = $pdo->prepare("SELECT id FROM products WHERE category_id=? AND slug=?"); $ex->execute([$cid,$p['slug']]);
    $pid = $ex->fetchColumn();
    if ($pid) {
        $pdo->prepare("UPDATE products SET title=?,description=?,status='published',sort_order=? WHERE id=?")->execute([$p['title'],$p['description_en'],$p['sort_order'],$pid]);
    } else {
        $pdo->prepare("INSERT INTO products(category_id,title,slug,description,status,sort_order) VALUES(?,?,?,?,'published',?)")->execute([$cid,$p['title'],$p['slug'],$p['description_en'],$p['sort_order']]);
        $pid = (int)$pdo->lastInsertId();
    }
    $tr->execute(['product',(int)$pid,'description','ar',$p['description_ar']]);
    // wipe old spec rows + their translations
    $old = $pdo->prepare("SELECT sr.id FROM spec_rows sr JOIN spec_groups sg ON sg.id=sr.group_id WHERE sg.product_id=?");
    $old->execute([$pid]);
    foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $rid) $pdo->prepare("DELETE FROM translations WHERE entity='spec_row' AND entity_id=?")->execute([(int)$rid]);
    $pdo->prepare("DELETE FROM spec_groups WHERE product_id=?")->execute([$pid]);
    $gi=0; $nr=0;
    foreach ($p['groups'] as $g) {
        $pdo->prepare("INSERT INTO spec_groups(product_id,name,sort_order) VALUES(?,?,?)")->execute([$pid,$g['name'],$gi++]);
        $gid=(int)$pdo->lastInsertId(); $ri=0;
        foreach ($g['rows'] as $r) {
            $pdo->prepare("INSERT INTO spec_rows(group_id,name,value,sort_order) VALUES(?,?,?,?)")->execute([$gid,$r['name_en'],$r['value_en']??'',$ri++]);
            $rid=(int)$pdo->lastInsertId(); $nr++;
            if (!empty($r['name_ar'])) $tr->execute(['spec_row',$rid,'name','ar',$r['name_ar']]);
            if (!empty($r['value_ar'])) $tr->execute(['spec_row',$rid,'value','ar',$r['value_ar']]);
        }
    }
    if ($p['slug']==='sy145' && $sy145media) {
        $pdo->prepare("DELETE FROM media WHERE product_id=?")->execute([$pid]);
        foreach ($sy145media as $m) $pdo->prepare("INSERT INTO media(product_id,path,kind,caption,is_main,sort_order) VALUES(?,?,?,?,?,0)")->execute([$pid,$m['path'],($m['kind']??'slider')==='extra'?'extra':'slider',$m['caption']??null,(int)($m['is_main']??0)]);
    }
    $report[] = "{$p['slug']}#$pid rows=$nr";
}
echo implode("\n", $report), "\nseeded OK\n";

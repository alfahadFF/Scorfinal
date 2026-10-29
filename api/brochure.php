<?php
/* SCOR product brochure generator (one.com, mPDF).
   Builds the 2-page A4 short brochure for one model from the model's own
   website page (single source of truth):
     page 1: header band, model name, hero photo, intro, key spec groups
     page 2: engineering drawing (if the model has one), dimension groups, notes
   GET /api/brochure.php?lang=ar&cat=skid-steer-loaders&model=sy145
*/
declare(strict_types=0);
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$ROOT = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($ROOT === '') $ROOT = dirname(__DIR__);
$LANGS = ['en', 'ar', 'de', 'tr'];
$lang = $_GET['lang'] ?? 'en';
$cat  = $_GET['cat'] ?? '';
$model = $_GET['model'] ?? '';
if (!in_array($lang, $LANGS, true)
    || !preg_match('/^[a-z0-9-]+$/', $cat)
    || !preg_match('/^[a-z0-9-]+$/', $model)) {
    http_response_code(400); exit('Bad request');
}
$pageFile = "$ROOT/$lang/products/$cat/$model.html";
if (!is_file($pageFile)) { http_response_code(404); exit('Model not found'); }

require __DIR__ . '/mpdf/vendor/autoload.php';

/* ---------------- extract product data from the model page -------------- */
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTMLFile($pageFile);
libxml_clear_errors();
$xp = new DOMXPath($dom);

function xtext($xp, $query, $ctx = null) {
    $n = $ctx ? $xp->query($query, $ctx) : $xp->query($query);
    return ($n && $n->length) ? trim(preg_replace('/\s+/', ' ', $n->item(0)->textContent)) : '';
}
$h1 = xtext($xp, '//h1');
$code = preg_replace('/^SCOR\s+/i', '', $h1);
$code = trim($code) !== '' ? trim($code) : strtoupper($model);
$code = preg_replace('/([A-Za-z])\s+(\d)/', '$1$2', $code);
$display = 'SCOR ' . $code;
$intro = xtext($xp, '(//div[contains(concat(" ",normalize-space(@class)," ")," description ")])[1]');
if (mb_strlen($intro) > 380) {
    $intro = rtrim(mb_substr($intro, 0, 380));
    $intro = preg_replace('/\s+\S*$/', '', $intro) . '…';
}

/* spec groups: header li (empty value) starts a group */
$groups = [];
$lis = $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," specGroup ")]//li[contains(concat(" ",normalize-space(@class)," ")," spec ")]');
foreach ($lis as $li) {
    $k = xtext($xp, './/*[contains(concat(" ",normalize-space(@class)," ")," key ")]', $li);
    $v = xtext($xp, './/*[contains(concat(" ",normalize-space(@class)," ")," value ")]', $li);
    if ($k === '') continue;
    if ($v === '') { $groups[] = ['title' => $k, 'rows' => []]; continue; }
    if (!$groups) $groups[] = ['title' => '', 'rows' => []];
    $groups[count($groups) - 1]['rows'][] = [$k, $v];
}
$groups = array_values(array_filter($groups, function ($g) { return $g['rows'] || $g['title'] !== ''; }));

/* images: hero = model's own 1.* photo; drawing = d*.* engineering file */
function resolveSrc($src, $lang, $cat, $ROOT) {
    $p = parse_url($src, PHP_URL_PATH);
    if ($p[0] !== '/') $p = '/' . $lang . '/products/' . $cat . '/' . $p;
    $parts = [];
    foreach (explode('/', $p) as $seg) {
        if ($seg === '..') array_pop($parts);
        elseif ($seg !== '.' && $seg !== '') $parts[] = $seg;
    }
    return $ROOT . '/' . implode('/', $parts);
}
$hero = ''; $drawing = '';
foreach ($xp->query('//img') as $im) {
    $src = $im->getAttribute('src');
    if (strpos($src, 'scor-products/') === false) continue;
    $base = basename(parse_url($src, PHP_URL_PATH));
    $abs = resolveSrc($src, $lang, $cat, $ROOT);
    if (!$drawing && preg_match('/^d\d+\./i', $base)) $drawing = $abs;
    if (!$hero && preg_match('/^1\./', $base) && strpos($src, "/$model/") !== false) $hero = $abs;
    if (!$hero && strpos($src, "/$model/") !== false) $hero = $abs;
}
if ($hero && !is_file($hero)) $hero = '';
if ($drawing && !is_file($drawing)) $drawing = '';
/* photos/drawings may live in a disk folder whose name differs from the
   page slug (e.g. slug pump-pm-30 -> folder pm30) or not be referenced */
$assetDir = $hero ? dirname($hero) : '';
if (!$hero || !$drawing) {
    $stem = preg_replace('/^(pump|mixer)-/', '', $model);
    $dirs = array_unique([$model, str_replace('-', '', $model), $stem, str_replace('-', '', $stem)]);
    foreach ($dirs as $d) {
        $dir = $ROOT . '/assets/img/scor-products/' . $d;
        if (!is_dir($dir)) continue;
        if (!$hero) {
            $ones = glob($dir . '/1.{jpg,jpeg,png,JPG,PNG}', GLOB_BRACE) ?: [];
            if ($ones) { $hero = $ones[0]; $assetDir = $dir; }
        }
        if ($hero) break;
    }
}
if (!$drawing && $assetDir) {
    $cands = glob($assetDir . '/d*.{jpg,jpeg,png,JPG,PNG}', GLOB_BRACE) ?: [];
    sort($cands);
    if ($cands) $drawing = $cands[0];
}

/* split groups: dimensions + notes go to page 2. Key-only "groups"
   (note lines stored as header rows on the pages) join the notes. */
$isDims  = function ($t) { return preg_match('/أبعاد|dimension|abmessung|boyut|ölçü/i', $t); };
$isNotes = function ($t) { return preg_match('/ملاحظات|notes|hinweis|notlar|bemerk/i', $t); };
$p1 = []; $dims = []; $noteLines = [];
foreach ($groups as $g) {
    if ($isDims($g['title']) && $g['rows']) { $dims[] = $g; continue; }
    if ($isNotes($g['title']) || !$g['rows']) {
        if ($g['title'] !== '' && !$isNotes($g['title'])) $noteLines[] = $g['title'];
        foreach ($g['rows'] as $r) $noteLines[] = $r[0] . ($r[1] !== '' ? ': ' . $r[1] : '');
        continue;
    }
    $p1[] = $g;
}

/* dimension-letter legend for letter-coded dimension tables (A-S) */
$LEGEND = [
 'ar' => ['A'=>'الطول الكلي مع الدلو','B'=>'الطول بدون الدلو','C'=>'قاعدة العجلات','D'=>'الارتفاع الكلي','E'=>'ارتفاع الكابينة','F'=>'ارتفاع محور الدوران','G'=>'ارتفاع الدلو في أقصى وضع','H'=>'ارتفاع التفريغ','I'=>'مدى التفريغ','J'=>'زاوية إرجاع الدلو عند الرفع','K'=>'زاوية التفريغ عند أقصى ارتفاع','L'=>'عمق الحفر أسفل مستوى الأرض','M'=>'الخلوص الأرضي','N'=>'العرض الكلي','O'=>'عرض المسار','P'=>'عرض الدلو','Q'=>'عرض الدلو الداخلي','R'=>'نصف قطر الدوران الأمامي (بدون دلو)','S'=>'نصف قطر الدوران الأمامي (مع دلو)'],
 'en' => ['A'=>'Overall length with bucket','B'=>'Length without bucket','C'=>'Wheelbase','D'=>'Overall height','E'=>'Cab height','F'=>'Hinge-pin height','G'=>'Bucket height at maximum lift','H'=>'Dump height','I'=>'Dump reach','J'=>'Bucket rollback angle at lift','K'=>'Dump angle at max. height','L'=>'Dig depth below ground level','M'=>'Ground clearance','N'=>'Overall width','O'=>'Track width','P'=>'Bucket width','Q'=>'Bucket inner width','R'=>'Front turning radius (without bucket)','S'=>'Front turning radius (with bucket)'],
 'de' => ['A'=>'Gesamtlänge mit Schaufel','B'=>'Länge ohne Schaufel','C'=>'Radstand','D'=>'Gesamthöhe','E'=>'Kabinenhöhe','F'=>'Drehzapfenhöhe','G'=>'Schaufelhöhe bei max. Hub','H'=>'Auskipphöhe','I'=>'Auswurfweite','J'=>'Rückkippwinkel bei Hub','K'=>'Auskippwinkel bei max. Höhe','L'=>'Grabtiefe unter Boden','M'=>'Bodenfreiheit','N'=>'Gesamtbreite','O'=>'Spurweite','P'=>'Schaufelbreite','Q'=>'Schaufel-Innenbreite','R'=>'Vorderer Wenderadius (ohne Schaufel)','S'=>'Vorderer Wenderadius (mit Schaufel)'],
 'tr' => ['A'=>'Kovalı toplam uzunluk','B'=>'Kovasız uzunluk','C'=>'Dingil mesafesi','D'=>'Toplam yükseklik','E'=>'Kabin yüksekliği','F'=>'Mafsal pimi yüksekliği','G'=>'Maks. kaldırmada kova yüksekliği','H'=>'Boşaltma yüksekliği','I'=>'Boşaltma erişimi','J'=>'Kaldırmada kova geri dönüş açısı','K'=>'Maks. yükseklikte boşaltma açısı','L'=>'Zemin altı kazma derinliği','M'=>'Yerden yükseklik','N'=>'Toplam genişlik','O'=>'İz genişliği','P'=>'Kova genişliği','Q'=>'Kova iç genişliği','R'=>'Ön dönüş yarıçapı (kovasız)','S'=>'Ön dönüş yarıçapı (kovalı)'],
];
$COLHEADS = [
 'ar' => ['الرمز', 'الوصف', 'القيمة'],
 'en' => ['Ref.', 'Description', 'Value'],
 'de' => ['Pos.', 'Bezeichnung', 'Wert'],
 'tr' => ['Harf', 'Açıklama', 'Değer'],
];
function letterOf($key) {
    return preg_match('/^(?:البعد|Dimension|Abmessung|Boyut)\s+([A-S])$/u', trim($key), $m) ? $m[1] : null;
}
function isLetterGroup($g) {
    if (count($g['rows']) < 5) return false;
    $n = 0;
    foreach ($g['rows'] as $r) if (letterOf($r[0])) $n++;
    return $n >= 5 && $n >= count($g['rows']) * 0.6;
}

/* ---------------- localized chrome strings ------------------------------ */
$L = [
 'en' => ['brand' => 'SCOR Heavy Equipment & International Shipping', 'hq' => 'Head Office (Germany)',
   'homs' => 'Homs Office', 'daraa' => 'Daraa Office', 'photo' => 'Main specifications',
   'notes' => 'Notes', 'legal' => ['Dimensions and specifications are approximate and may vary depending on equipment or tires used.', 'Images and drawings are for illustrative purposes only.', 'SCOR reserves the right to change specifications without prior notice.']],
 'de' => ['brand' => 'SCOR Baumaschinen & internationaler Versand', 'hq' => 'Hauptsitz (Deutschland)',
   'homs' => 'Büro Homs', 'daraa' => 'Büro Daraa', 'photo' => 'Technische Daten',
   'notes' => 'Hinweise', 'legal' => ['Die angegebenen Abmessungen und Spezifikationen sind Näherungswerte und können je nach Ausstattung oder Bereifung abweichen.', 'Bilder und Zeichnungen dienen nur zur Veranschaulichung.', 'SCOR behält sich Änderungen der Spezifikationen ohne vorherige Ankündigung vor.']],
 'tr' => ['brand' => 'SCOR Ağır Ekipman ve Uluslararası Nakliye', 'hq' => 'Merkez Ofis (Almanya)',
   'homs' => 'Humus Ofisi', 'daraa' => 'Dera Ofisi', 'photo' => 'Temel özellikler',
   'notes' => 'Notlar', 'legal' => ['Belirtilen boyutlar ve özellikler yaklaşıktır; donanım veya lastiklere göre değişebilir.', 'Görseller ve çizimler yalnızca tanıtım amaçlıdır.', 'SCOR, özelliklerde önceden haber vermeksizin değişiklik yapma hakkını saklı tutar.']],
 'ar' => ['brand' => 'شركة سكور للمعدات الثقيلة والشحن الدولي', 'hq' => 'الإدارة العامة (ألمانيا)',
   'homs' => 'مكتب حمص', 'daraa' => 'مكتب درعا', 'photo' => 'المواصفات الرئيسية',
   'notes' => 'ملاحظات', 'legal' => ['الأبعاد والمواصفات المذكورة تقريبية وقد تختلف حسب نوع الدلو أو الإطارات المستخدمة.', 'الصور والرسومات لأغراض توضيحية فقط.', 'تحتفظ شركة SCOR بالحق في إجراء أي تعديلات على المواصفات دون إشعار مسبق.']],
][$lang];
$rtl = $lang === 'ar';
$GLOBALS['lang'] = $lang; $GLOBALS['rtl'] = $rtl;
$font = 'scorfont';
$logo = $ROOT . '/assets/img/companies/scor-logo-white.png';
$stripes = $ROOT . '/assets/img/brochure/header-band.png';
$wedge = $ROOT . '/assets/img/brochure/wedge.png';

/* ---------------- HTML builders ------------------------------------------ */
function groupTable($g, $wedge) {
    if (!$g['rows'] && $g['title'] === '') return '';
    if (isLetterGroup($g)) {
        $lg = $GLOBALS['LEGEND'][$GLOBALS['lang']] ?? [];
        $ch = $GLOBALS['COLHEADS'][$GLOBALS['lang']] ?? ['Ref.', 'Description', 'Value'];
        $h = '<table class="grp" width="100%" cellpadding="0" cellspacing="0">';
        if ($g['title'] !== '') {
            $w = '<td class="gh-w"><img src="' . $wedge . '" width="9mm" /></td>';
            $tt = '<td class="gh">' . htmlspecialchars($g['title']) . '</td>';
            $h .= '<tr>' . ($GLOBALS['rtl'] ? $tt . $w : $w . $tt) . '</tr>';
        }
        $h .= '<tr><td colspan="2"><table class="rows rows3" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr class="ch"><td class="c1">' . $ch[0] . '</td><td class="c2">' . $ch[1] . '</td><td class="c3">' . $ch[2] . '</td></tr>';
        $i = 0;
        foreach ($g['rows'] as $r) {
            $L = letterOf($r[0]);
            $cls = ($i++ % 2) ? ' class="alt"' : '';
            $v3 = str_replace('\u{2713}', '<span style="font-family:dejavusans">&#10003;</span>', htmlspecialchars($r[1]));
            $h .= "<tr$cls><td class=\"c1 sym\">" . $L . '</td><td class="c2">' . htmlspecialchars($lg[$L] ?? '') . '</td><td class="c3">' . $v3 . '</td></tr>';
        }
        return $h . '</table></td></tr></table>';
    }
    $h = '<table class="grp" width="100%" cellpadding="0" cellspacing="0">';
    if ($g['title'] !== '') {
        $w = '<td class="gh-w"><img src="' . $wedge . '" width="9mm" /></td>';
        $t = '<td class="gh">' . htmlspecialchars($g['title']) . '</td>';
        $h .= '<tr>' . ($GLOBALS['rtl'] ? $t . $w : $w . $t) . '</tr>';
    }
    $h .= '<tr><td colspan="2"><table class="rows" width="100%" cellpadding="0" cellspacing="0">';
    $i = 0;
    foreach ($g['rows'] as $r) {
        $cls = ($i++ % 2) ? ' class="alt"' : '';
        $v = str_replace('\u{2713}', '<span style="font-family:dejavusans">&#10003;</span>', htmlspecialchars($r[1]));
        $h .= "<tr$cls><td class=\"k\">" . htmlspecialchars($r[0]) . '</td><td class="v">' . $v . '</td></tr>';
    }
    return $h . '</table></td></tr></table>';
}
function estH($g) {
    $kd = $GLOBALS['rtl'] ? 24 : 30;
    $vd = $GLOBALS['rtl'] ? 30 : 38;
    $h = 2.4;
    foreach ($g['rows'] as $r) $h += 1 + intdiv(mb_strlen($r[0]), $kd) + intdiv(mb_strlen($r[1]), $vd);
    return $h;
}
function columns($gs, $wedge) {
    if (!$gs) return '';
    if (count($gs) === 1 && count($gs[0]['rows']) > 12) {
        $half = (int) ceil(count($gs[0]['rows']) / 2);
        $gs = [
            ['title' => $gs[0]['title'], 'rows' => array_slice($gs[0]['rows'], 0, $half)],
            ['title' => '', 'rows' => array_slice($gs[0]['rows'], $half)],
        ];
    }
    $colA = []; $colB = []; $wa = 0; $wb = 0;
    foreach ($gs as $g) {
        if ($wa <= $wb) { $colA[] = $g; $wa += estH($g); }
        else { $colB[] = $g; $wb += estH($g); }
    }
    $ca = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $colA));
    $cb = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $colB));
    return '<table class="cols" width="100%" cellpadding="0" cellspacing="0"><tr>'
         . '<td class="col">' . $ca . '</td><td class="gap"></td><td class="col">' . $cb . '</td></tr></table>';
}

$customBand = $ROOT . '/assets/img/brochure/header-custom.png';
$bandFile = is_file($customBand) ? $customBand : $stripes;
$band = '<img src="' . $bandFile . '" style="width:210mm;margin-left:-9mm" />';
$bandH = 25;
if (is_file($bandFile)) { $sz = getimagesize($bandFile); if ($sz) $bandH = 210 * $sz[1] / $sz[0]; }
$pin = '<span style="color:#f5b400">&#8226;</span> ';
$foot = '<table width="100%" cellpadding="0" cellspacing="0" dir="ltr" style="background:#0c0d10;border-top:1.2mm solid #f5b400;width:210mm;margin-left:-9mm"><tr>'
      . '<td width="31%" style="padding:3mm 5mm"><img src="' . $logo . '" height="10mm" /><div class="fb">' . $L['brand'] . '<br/>SCOR Heavy Equipment &amp; International Shipping</div></td>'
      . '<td class="fc" width="23%">' . $pin . '<b>' . $L['hq'] . '</b><br/>004915118316638<br/>004917634389434</td>'
      . '<td class="fc" width="17%">' . $pin . '<b>' . $L['homs'] . '</b><br/>00963939062887</td>'
      . '<td class="fc" width="15%">' . $pin . '<b>' . $L['daraa'] . '</b><br/>00963941435541</td>'
      . '<td class="fc" width="14%">www.scor-sy.de<br/>info@scor-sy.de</td></tr></table>';

/* move angle rows out of page-1 groups into a page-2 Working Angles group */
$ANGLES = ['ar' => 'زوايا العمل', 'en' => 'Working Angles', 'de' => 'Arbeitswinkel', 'tr' => 'Çalışma Açıları'][$lang];
$angleRows = [];
foreach ($p1 as $gi => $g) {
    $keep = [];
    foreach ($g['rows'] as $r) {
        if (preg_match('/زاوية|angle|winkel|açı/i', $r[0])) $angleRows[] = $r;
        else $keep[] = $r;
    }
    $p1[$gi]['rows'] = $keep;
}
$p1 = array_values(array_filter($p1, function ($g) { return $g['rows']; }));
$noteRows = $noteLines;
if (count($noteRows) < 2) foreach ($L['legal'] as $line) $noteRows[] = $line;
$p2groups = $dims;
if (count($angleRows) >= 2) $p2groups[] = ['title' => $ANGLES, 'rows' => $angleRows];
if ($noteRows) $p2groups[] = ['title' => $L['notes'], 'rows' => array_map(function ($n) { return [$n, '']; }, $noteRows)];

/* fill-the-page density: estimate column heights and stretch row padding */
function colUnits($gs) {
    if (count($gs) === 1 && count($gs[0]['rows']) > 12) {
        $half = (int) ceil(count($gs[0]['rows']) / 2);
        $gs = [
            ['title' => $gs[0]['title'], 'rows' => array_slice($gs[0]['rows'], 0, $half)],
            ['title' => '', 'rows' => array_slice($gs[0]['rows'], $half)],
        ];
    }
    $wa = 0; $wb = 0;
    foreach ($gs as $g) { if ($wa <= $wb) $wa += estH($g); else $wb += estH($g); }
    return max($wa, $wb);
}
$BODYH = 297 - (int) round($bandH + 5) - 24;
$heroH = 0;
if ($hero) { $hs = getimagesize($hero); if ($hs) $heroH = min(56, $heroW * $hs[1] / $hs[0]) + 3; }
elseif ($intro) $heroH = 20;
$avail1 = $BODYH - 15 - $heroH;
$units1 = colUnits($p1);
$pad1 = 1.0;
foreach ([1.9, 1.65, 1.4, 1.2, 1.05] as $cand) {
    if ($units1 * (3.4 + 2 * $cand) <= $avail1 * 0.94) { $pad1 = $cand; break; }
}
$drawCap = 84; $drawH = 0;
if ($drawing) { $ds = getimagesize($drawing); if ($ds) $drawH = min($drawCap, 190 * $ds[1] / $ds[0]) + 4; }
$notesH = $noteLines || true ? 0 : 0;
$avail2 = $BODYH - 15 - $drawH;
$units2 = colUnits($p2groups);
$pad2 = 1.0;
foreach ([1.9, 1.65, 1.4, 1.2, 1.05] as $cand) {
    if ($units2 * (3.4 + 2 * $cand) + 34 <= $avail2 * 0.94) { $pad2 = $cand; break; }
}
if ($drawing && $units2 * (3.4 + 2 * $pad2) + 34 <= $avail2 - 40) $drawCap = 98;

$title = '<div class="model">' . htmlspecialchars($display) . '</div>';
$p1html = $title;
$p1rows = 0; foreach ($p1 as $g) $p1rows += count($g['rows']);
$heroW = $p1rows <= 26 ? 94 : ($p1rows <= 42 ? 86 : 79);
if ($hero || $intro) {
    $photoCell = $hero ? '<td class="ph" style="width:' . ($heroW + 2) . 'mm"><img src="' . $hero . '" style="width:' . $heroW . 'mm;max-height:56mm" /></td>' : '';
    $textCell = '<td class="tx">' . htmlspecialchars($intro) . '</td>';
    $cells = $rtl ? $photoCell . $textCell : $textCell . $photoCell;
    $p1html .= '<table class="hero" width="100%" cellpadding="0" cellspacing="0"><tr>' . $cells . '</tr></table>';
}
$p1html .= columns($p1, $wedge);

$p2html = $title;
if ($drawing) {
    $p2html .= '<div class="draw"><img src="' . $drawing . '" style="max-height:' . $drawCap . 'mm;max-width:100%" /></div>';
}
$p2html .= columns($p2groups, $wedge);


$css = '
body { font-family: ' . $font . '; color:#151515; }
.model { font-size:25pt; font-weight:bold; color:#4a4a4a; padding:2mm 0 3mm; ' . ($rtl ? 'text-align:right;' : '') . ' }
.hero td { vertical-align:top; }
.hero .ph { width:88mm; }
.hero .tx { font-size:10.5pt; font-weight:bold; line-height:1.9; padding:1mm 4mm; }
.cols td.col { width:48.5%; vertical-align:top; }
.cols td.gap { width:3%; }
.grp { margin-bottom:3.5mm; }
.gh { background:#151515; color:#fff; font-weight:bold; font-size:11pt; padding:1.5mm 3mm; }
.gh-w { background:#151515; width:9mm; }
.rows td { font-size:10pt; font-weight:bold; padding:1.0mm 2.6mm; border-bottom:0.2mm solid #d9d9d9; }
.rows tr.alt td { background:#e9e9e9; }
.rows td.k { font-weight:bold; width:52%; }
.draw { text-align:center; padding:2mm 0 4mm; }
.rows3 td.c1 { width:11%; text-align:center; }
.rows3 td.c2 { width:57%; }
.rows3 td.c3 { width:32%; font-weight:bold; }
.rows3 td.sym { font-weight:bold; font-size:11pt; }
.rows3 tr.ch td { background:#3a3a3a; color:#fff; font-weight:bold; font-size:9pt; }
.nbox { background:#f2f2f2; padding:2.5mm 3mm; }
.nline { font-size:9pt; font-weight:bold; line-height:1.7; }
.fb { color:#f5b400; font-size:7pt; padding-top:1.2mm; }
.fc { color:#fff; font-size:7.3pt; line-height:1.55; padding:3mm 2mm; vertical-align:top; }
.fc b { color:#f5b400; }
';

/* ---------------- render -------------------------------------------------- */
$cfg = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8', 'format' => 'A4',
    'margin_left' => 9, 'margin_right' => 9, 'margin_top' => (int) round($bandH + 5), 'margin_bottom' => 24,
    'margin_header' => 0, 'margin_footer' => 0,
    'fontDir' => array_merge($cfg['fontDir'], [__DIR__ . '/fonts']),
    'fontdata' => $fontData + ['scorfont' => ['R' => 'Cairo-400.ttf', 'B' => 'Cairo-700.ttf', 'I' => 'Cairo-400.ttf', 'BI' => 'Cairo-700.ttf', 'useOTL' => 0xFF], 'Tajawal' => ['R' => 'Tajawal-400.ttf', 'B' => 'Tajawal-700.ttf', 'I' => 'Tajawal-500.ttf', 'BI' => 'Tajawal-800.ttf']],
    'default_font' => $font,
    'tempDir' => __DIR__ . '/mpdf/tmp3',
]);
if ($rtl) $mpdf->SetDirectionality('rtl');
$mpdf->SetTitle($display . ' — SCOR Brochure');
$customFoot = $ROOT . '/assets/img/brochure/footer-custom.png';
$footBand = is_file($customFoot) ? $customFoot : $ROOT . '/assets/img/brochure/footer-band.png';
$foot1 = '<img src="' . $footBand . '" style="width:210mm;margin-left:-9mm" />';
$mpdf->SetHTMLHeader($band);
$mpdf->SetHTMLFooter($foot1);
try {
    $dens1 = '.rows td{padding:' . $pad1 . 'mm 2.6mm}.grp{margin-bottom:' . (3 + ($pad1 - 1) * 3) . 'mm}';
    $dens2 = '.rows td{padding:' . $pad2 . 'mm 2.6mm}.grp{margin-bottom:' . (3 + ($pad2 - 1) * 3) . 'mm}';
    $mpdf->WriteHTML('<style>' . $css . $dens1 . '</style>' . $p1html . '<pagebreak />');
    $mpdf->SetHTMLFooter($foot);
    $mpdf->WriteHTML('<style>' . $dens2 . '</style>' . $p2html);
} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    exit('Brochure generation failed.');
}
$file = 'SCOR_' . preg_replace('/\s+/', '', $code) . '_Brochure_' . strtoupper($lang) . '.pdf';
while (ob_get_level()) ob_end_clean();
$mpdf->Output($file, 'D');

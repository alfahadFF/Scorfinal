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

$ROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/');
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
/* drawing may exist on disk without being referenced by the page */
if (!$drawing && $hero) {
    $cands = glob(dirname($hero) . '/d*.{jpg,jpeg,png,JPG,PNG}', GLOB_BRACE) ?: [];
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
$font = $rtl ? 'Tajawal' : 'dejavusans';
$logo = $ROOT . '/assets/img/companies/scor-logo-white.png';
$stripes = $ROOT . '/assets/img/brochure/stripes.png';
$wedge = $ROOT . '/assets/img/brochure/wedge.png';

/* ---------------- HTML builders ------------------------------------------ */
function groupTable($g, $wedge) {
    if (!$g['rows'] && $g['title'] === '') return '';
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
        $h .= "<tr$cls><td class=\"k\">" . htmlspecialchars($r[0]) . '</td><td class="v">' . htmlspecialchars($r[1]) . '</td></tr>';
    }
    return $h . '</table></td></tr></table>';
}
function columns($gs, $wedge) {
    if (!$gs) return '';
    $colA = []; $colB = []; $wa = 0; $wb = 0;
    foreach ($gs as $g) {
        if ($wa <= $wb) { $colA[] = $g; $wa += count($g['rows']) + 2; }
        else { $colB[] = $g; $wb += count($g['rows']) + 2; }
    }
    $ca = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $colA));
    $cb = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $colB));
    return '<table class="cols" width="100%" cellpadding="0" cellspacing="0"><tr>'
         . '<td class="col">' . $ca . '</td><td class="gap"></td><td class="col">' . $cb . '</td></tr></table>';
}

$band = '<table width="100%" cellpadding="0" cellspacing="0" dir="ltr" style="background:#0c0d10"><tr>'
      . '<td width="40%" style="padding:4mm 6mm"><img src="' . $logo . '" height="11mm" /></td>'
      . '<td width="60%" align="right"><img src="' . $stripes . '" height="19mm" /></td></tr></table>';
$foot = '<table width="100%" cellpadding="0" cellspacing="0" style="background:#0c0d10;border-top:1.2mm solid #f5b400"><tr>'
      . '<td width="30%" style="padding:3mm 5mm"><img src="' . $logo . '" height="10mm" /><div class="fb">' . $L['brand'] . '</div></td>'
      . '<td class="fc" width="24%"><b>' . $L['hq'] . '</b><br/>004915118316638<br/>004917634389434</td>'
      . '<td class="fc" width="17%"><b>' . $L['homs'] . '</b><br/>00963939062887</td>'
      . '<td class="fc" width="15%"><b>' . $L['daraa'] . '</b><br/>00963941435541</td>'
      . '<td class="fc" width="14%">www.scor-sy.de<br/>info@scor-sy.de</td></tr></table>';

$title = '<div class="model">' . htmlspecialchars($display) . '</div>';
$p1html = $title;
if ($hero || $intro) {
    $photoCell = $hero ? '<td class="ph"><img src="' . $hero . '" style="width:82mm" /></td>' : '';
    $textCell = '<td class="tx">' . htmlspecialchars($intro) . '</td>';
    $cells = $rtl ? $photoCell . $textCell : $textCell . $photoCell;
    $p1html .= '<table class="hero" width="100%" cellpadding="0" cellspacing="0"><tr>' . $cells . '</tr></table>';
}
$p1html .= columns($p1, $wedge);

$p2html = $title;
if ($drawing) {
    $p2html .= '<div class="draw"><img src="' . $drawing . '" style="max-height:78mm;max-width:100%" /></div>';
}
$p2html .= columns($dims, $wedge);
$noteRows = $noteLines;
if (count($noteRows) < 2) foreach ($L['legal'] as $line) $noteRows[] = $line;
if ($noteRows) {
    $items = implode('', array_map(function ($n) { return '<div class="nline">' . htmlspecialchars($n) . '</div>'; }, $noteRows));
    $w = '<td class="gh-w"><img src="' . $wedge . '" width="9mm" /></td>';
    $t = '<td class="gh">' . $L['notes'] . '</td>';
    $p2html .= '<table class="grp" width="100%" cellpadding="0" cellspacing="0"><tr>' . ($rtl ? $t . $w : $w . $t) . '</tr>'
             . '<tr><td colspan="2" class="nbox">' . $items . '</td></tr></table>';
}

$css = '
body { font-family: ' . $font . '; color:#151515; }
.model { font-size:25pt; font-weight:bold; color:#4a4a4a; padding:2mm 0 3mm; ' . ($rtl ? 'text-align:right;' : '') . ' }
.hero td { vertical-align:top; }
.hero .ph { width:84mm; }
.hero .tx { font-size:9.5pt; line-height:1.7; padding:1mm 4mm; }
.cols td.col { width:48.5%; vertical-align:top; }
.cols td.gap { width:3%; }
.grp { margin-bottom:3.5mm; }
.gh { background:#151515; color:#fff; font-weight:bold; font-size:10pt; padding:1.5mm 3mm; }
.gh-w { background:#151515; width:9mm; }
.rows td { font-size:8pt; padding:1.0mm 2.4mm; border-bottom:0.2mm solid #d9d9d9; }
.rows tr.alt td { background:#e9e9e9; }
.rows td.k { font-weight:bold; width:52%; }
.draw { text-align:center; padding:2mm 0 4mm; }
.nbox { background:#f2f2f2; padding:2.5mm 3mm; }
.nline { font-size:8pt; line-height:1.6; }
.fb { color:#f5b400; font-size:7pt; padding-top:1.2mm; }
.fc { color:#fff; font-size:7.3pt; line-height:1.55; padding:3mm 2mm; vertical-align:top; }
.fc b { color:#f5b400; }
';

/* ---------------- render -------------------------------------------------- */
$cfg = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8', 'format' => 'A4',
    'margin_left' => 9, 'margin_right' => 9, 'margin_top' => 27, 'margin_bottom' => 24,
    'margin_header' => 0, 'margin_footer' => 0,
    'fontDir' => array_merge($cfg['fontDir'], [__DIR__ . '/fonts']),
    'fontdata' => $fontData + ['Tajawal' => ['R' => 'Tajawal-400.ttf', 'B' => 'Tajawal-700.ttf', 'I' => 'Tajawal-500.ttf', 'BI' => 'Tajawal-800.ttf']],
    'default_font' => $font,
    'tempDir' => __DIR__ . '/mpdf/tmp',
]);
if ($rtl) $mpdf->SetDirectionality('rtl');
$mpdf->SetTitle($display . ' — SCOR Brochure');
$mpdf->SetHTMLHeader($band);
$mpdf->SetHTMLFooter($foot);
try {
    $mpdf->WriteHTML('<style>' . $css . '</style>' . $p1html . '<pagebreak />' . $p2html);
} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    exit('Brochure generation failed.');
}
$file = 'SCOR_' . preg_replace('/\s+/', '', $code) . '_Brochure_' . strtoupper($lang) . '.pdf';
while (ob_get_level()) ob_end_clean();
$mpdf->Output($file, 'D');

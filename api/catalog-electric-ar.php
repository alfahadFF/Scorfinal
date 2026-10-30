<?php
/* SCOR electric-forklift catalog (Arabic only, one.com, mPDF).
   Builds one comprehensive A4 PDF for the whole electric-forklift range from
   the models' own Arabic website pages (single source of truth):
     cover + model overview, one page per model (photo, intro, full specs),
     closing contact page. Spec VALUES keep their numbers/units verbatim;
     English descriptive values are translated to Arabic via the map below. */
declare(strict_types=0);
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

$ROOT = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($ROOT === '') $ROOT = dirname(__DIR__);
$LANG = 'ar';
$CAT = 'electric-forklifts';

require __DIR__ . '/mpdf/vendor/autoload.php';

function norm($s) { return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', (string)$s)); }

/* Arabic translations of English descriptive spec values (numbers/units kept verbatim). */
$TR = [
 '1.5KW power unit' => 'وحدة قدرة 1.5KW',
 '1000W power unit' => 'وحدة قدرة 1000W',
 '1300W power unit' => 'وحدة قدرة 1300W',
 '800W power unit' => 'وحدة قدرة 800W',
 '100mm C-shaped steel door frame' => 'إطار باب فولاذي على شكل C بسماكة 100mm',
 '120mm specialized steel door frame' => 'إطار باب فولاذي متخصص 120mm',
 '150mm specialized steel door frame' => 'إطار باب فولاذي متخصص 150mm',
 '1070mm integral forging fork' => 'شوكة مطروقة متكاملة 1070mm',
 '920mm integral forging fork' => 'شوكة مطروقة متكاملة 920mm',
 '1220mm body forged fork' => 'شوكة هيكل مطروقة 1220mm',
 '1370mm body forged fork' => 'شوكة هيكل مطروقة 1370mm',
 '10KW brushless motor' => 'محرك بدون فرش 10KW',
 '10kw brushless motor' => 'محرك بدون فرش 10kw',
 '1200w brushless motor' => 'محرك بدون فرش 1200w',
 '1500w brushless motor' => 'محرك بدون فرش 1500w',
 '2.5KW brushless motor' => 'محرك بدون فرش 2.5KW',
 '3KW brushless AC motor' => 'محرك AC بدون فرش 3KW',
 '3kw brushless motor' => 'محرك بدون فرش 3kw',
 '4KW brushless motor' => 'محرك بدون فرش 4KW',
 '4kw brushless motor' => 'محرك بدون فرش 4kw',
 '5KW brushless motor' => 'محرك بدون فرش 5KW',
 '5kw brushless motor' => 'محرك بدون فرش 5kw',
 '7.5KW brushless motor' => 'محرك بدون فرش 7.5KW',
 '4 hours' => '4 ساعات',
 '5 hours' => '5 ساعات',
 '6 hours' => '6 ساعات',
 '6-8 hours' => '6-8 ساعات',
 '12 degrees' => '12 درجة',
 '24 tubes, 48v/60v universal' => '24 أنبوباً، متوافقة مع 48v/60v',
 '4 pieces of ultra-fine dry batteries (48v/45a)' => '4 بطاريات جافة فائقة الدقة (48v/45a)',
 '10 Ultra Micro Dry Batteries (60V/140AH)' => '10 بطاريات جافة فائقة الدقة (60V/140AH)',
 '10 Ultra Micro Dry Batteries (60V/170AH)' => '10 بطاريات جافة فائقة الدقة (60V/170AH)',
 '10 Ultra Micro Dry Batteries (60V/200AH)' => '10 بطاريات جافة فائقة الدقة (60V/200AH)',
 'Cast steel 220 aluminum box bridge' => 'جسر صندوقي من الفولاذ المصبوب والألمنيوم 220',
 'Cast steel integrated 220 AC bridge' => 'جسر AC متكامل من الفولاذ المصبوب 220',
 'Integrated steel pipe bridge' => 'جسر أنابيب فولاذية متكامل',
 'Fully suspended iron sheet integrated 310 bridge' => 'جسر 310 متكامل من الصاج الحديدي بتعليق كامل',
 'Electricity display meter' => 'مقياس عرض مستوى شحن البطارية',
 'Front wheel 400-8, rear wheel 300-10 (both high wear-resistant solid tires)' => 'عجلة أمامية 400-8، عجلة خلفية 300-10 (إطاران صلبان عاليا مقاومة التآكل)',
 'Front wheel 500-8, rear wheel 300-10 (both high wear-resistant solid tires)' => 'عجلة أمامية 500-8، عجلة خلفية 300-10 (إطاران صلبان عاليا مقاومة التآكل)',
 'Front wheel 500-8, rear wheel 400-8 (both high wear-resistant solid tires)' => 'عجلة أمامية 500-8، عجلة خلفية 400-8 (إطاران صلبان عاليا مقاومة التآكل)',
 'Front wheel 650-10 (A-grade high wear-resistant solid tire), rear wheel' => 'عجلة أمامية 650-10 (إطار صلب عالي مقاومة التآكل، درجة A)، عجلة خلفية',
 'Front wheel 650-10 (high wear-resistant solid tire), rear wheel 500-8 (high' => 'عجلة أمامية 650-10 (إطار صلب عالي مقاومة التآكل)، عجلة خلفية 500-8 (عالي',
 'Hand brake, mechanical brake' => 'فرامل يد، فرامل ميكانيكية',
 'Hand brake, oil brake' => 'فرامل يد، فرامل زيتية',
 'High precision machining of bidirectional single oil cylinder' => 'أسطوانة زيتية أحادية ثنائية الاتجاه مصنوعة بدقة عالية',
 'Unidirectional dual oil cylinder' => 'أسطوانة زيتية مزدوجة أحادية الاتجاه',
 'Hydraulic Power Steering' => 'توجيه هيدروليكي معزز',
 'Mechanical steering' => 'توجيه ميكانيكي',
 'Special rear axle for heavy-duty steel plate forklifts' => 'محور خلفي خاص لرافعات الألواح الفولاذية الثقيلة',
 'Thick integrated stamping body' => 'هيكل متكامل سميك مشكّل بالكبس',
 'Thickened integrated body' => 'هيكل متكامل مُسمّك',
 'Lighting fixtures, left and right turn signals, brake lights, running' => 'تجهيزات إضاءة، إشارات انعطاف يميناً ويساراً، أضواء فرامل، أضواء تشغيل',
 'Lighting fixtures, left and right turn signals, brake lights, running lights,' => 'تجهيزات إضاءة، إشارات انعطاف يميناً ويساراً، أضواء فرامل، أضواء تشغيل،',
];

/* ---- extraction from a model's own page (same rules as brochure.php) ---- */
function xtext($xp, $query, $ctx = null) {
    $n = $ctx ? $xp->query($query, $ctx) : $xp->query($query);
    return ($n && $n->length) ? norm($n->item(0)->textContent) : '';
}
function extractModel($ROOT, $lang, $cat, $model) {
    $pageFile = "$ROOT/$lang/products/$cat/$model.html";
    if (!is_file($pageFile)) return null;
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTMLFile($pageFile);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    $h1 = xtext($xp, '//h1');
    $code = trim(preg_replace('/^SCOR\s+/i', '', $h1)) ?: strtoupper($model);
    $code = preg_replace('/([A-Za-z])\s+(\d)/', '$1$2', $code);
    $intro = xtext($xp, '(//div[contains(concat(" ",normalize-space(@class)," ")," description ")])[1]');
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
    $groups = array_values(array_filter($groups, function ($g) { return $g['rows']; }));
    $hero = '';
    foreach ($xp->query('//img') as $im) {
        $src = $im->getAttribute('src');
        if (strpos($src, 'scor-products/') === false) continue;
        $base = basename(parse_url($src, PHP_URL_PATH));
        if (preg_match('/^1\./', $base) && strpos($src, "/$model/") !== false) { $hero = $src; break; }
    }
    if ($hero) {
        $p = parse_url($hero, PHP_URL_PATH);
        if ($p[0] !== '/') $p = '/' . $lang . '/products/' . $cat . '/' . $p;
        $parts = [];
        foreach (explode('/', $p) as $seg) {
            if ($seg === '..') array_pop($parts);
            elseif ($seg !== '.' && $seg !== '') $parts[] = $seg;
        }
        $hero = $ROOT . '/' . implode('/', $parts);
        if (!is_file($hero)) $hero = '';
    }
    if (!$hero) {
        $ones = glob($ROOT . '/assets/img/scor-products/' . $model . '/1.{jpg,jpeg,png,JPG,PNG}', GLOB_BRACE) ?: [];
        if ($ones) $hero = $ones[0];
    }
    if ($hero && preg_match('/^1\./', basename($hero))) {
        $cropFile = dirname($hero) . '/1-crop.jpg';
        if (is_file($cropFile)) $hero = $cropFile;
    }
    return ['code' => $code, 'intro' => $intro, 'groups' => $groups, 'hero' => $hero];
}

/* ---------------- table builders (brochure styling) --------------------- */
function isListGroup($g) {
    if (!$g['rows']) return false;
    foreach ($g['rows'] as $r) {
        $v = trim((string)$r[1]);
        if ($v !== '' && $v !== '✓' && $v !== '✔') return false;
    }
    return true;
}
function groupTable($g, $wedge) {
    if (!$g['rows'] && $g['title'] === '') return '';
    $h = '<table class="grp" width="100%" cellpadding="0" cellspacing="0">';
    if ($g['title'] !== '') {
        $w = '<td class="gh-w"><img src="' . $wedge . '" width="9mm" /></td>';
        $t = '<td class="gh">' . htmlspecialchars($g['title']) . '</td>';
        $h .= '<tr>' . $t . $w . '</tr>';
    }
    $h .= '<tr><td colspan="2"><table class="rows" width="100%" cellpadding="0" cellspacing="0">';
    $i = 0;
    foreach ($g['rows'] as $r) {
        $cls = ($i++ % 2) ? ' class="alt"' : '';
        if (isListGroup($g)) {
            $h .= "<tr$cls><td class=\"one\">" . htmlspecialchars($r[0]) . '</td></tr>';
        } else {
            $h .= "<tr$cls><td class=\"k\">" . htmlspecialchars($r[0]) . '</td><td class="v">' . htmlspecialchars($r[1]) . '</td></tr>';
        }
    }
    return $h . '</table></td></tr></table>';
}
function estH($g) {
    $h = 2.4;
    foreach ($g['rows'] as $r) $h += 1 + intdiv(mb_strlen($r[0]), 24) + intdiv(mb_strlen($r[1]), 30);
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

/* ---------------- collect models ---------------------------------------- */
$files = glob("$ROOT/$LANG/products/$CAT/*.html") ?: [];
$models = [];
foreach ($files as $f) {
    $stem = basename($f, '.html');
    if (!preg_match('/^ef\d+$/', $stem)) continue;
    $d = extractModel($ROOT, $LANG, $CAT, $stem);
    if ($d) $models[(int) substr($stem, 2)] = ['slug' => $stem] + $d;
}
ksort($models);
if (!$models) { http_response_code(404); exit('No models'); }

/* drop source-defect rows (value merely echoes a label, e.g. 'Drive axle (mm)')
   instead of inventing a number — accuracy first */
$skipped = [];
foreach ($models as &$m) {
    foreach ($m['groups'] as $gi => $g) {
        $keep = [];
        foreach ($g['rows'] as $r) {
            if (norm($r[1]) === 'Drive axle (mm)') { $skipped[] = $m['code'] . ': ' . $r[0]; continue; }
            $keep[] = [$r[0], $GLOBALS['TR'][norm($r[1])] ?? $r[1]];
        }
        $m['groups'][$gi]['rows'] = $keep;
    }
}
unset($m);

/* regroup rows into feature tables (same taxonomy as the one-page leaflet) */
$FEAT_T = ['الأداء', 'الأبعاد والأوزان', 'البطارية والنظام الكهربائي', 'المحركات والهيدروليك', 'الهيكل والتوجيه والفرامل'];
$FEAT_R = ['/حمولة|ارتفاع الرفع|زمن العمل|زاوية/u', '/طول|العرض|الارتفاع|قطر الدوران|وزن المركبة/u', '/بطاري|الجهد|الشحن|التحكم|الإشارة/u', '/محرك|محور الإدارة|محور الدفع|أسطوانة الرفع/u'];
foreach ($models as &$m) {
    $all = [];
    foreach ($m['groups'] as $g) $all = array_merge($all, $g['rows']);
    $b = [[], [], [], [], []];
    foreach ($all as $r) {
        $k = mb_strtolower($r[0], 'UTF-8');
        $bi = 4;
        foreach ($FEAT_R as $i => $re) { if (preg_match($re, $k)) { $bi = $i; break; } }
        $b[$bi][] = $r;
    }
    $m['groups'] = [];
    foreach ($b as $i => $rows) if ($rows) $m['groups'][] = ['title' => $FEAT_T[$i], 'rows' => $rows];
}
unset($m);
function specVal($m, $keyPart) {
    foreach ($m['groups'] as $g) foreach ($g['rows'] as $r)
        if (mb_strpos($r[0], $keyPart) !== false) return $r[1];
    return '—';
}

/* ---------------- chrome ------------------------------------------------- */
$bandFile = $ROOT . '/assets/img/brochure/header-custom.png';
if (!is_file($bandFile)) $bandFile = $ROOT . '/assets/img/brochure/header-band.png';
$band = '<img src="' . $bandFile . '" style="width:210mm;margin-left:-9mm" />';
$bandH = 25;
if (is_file($bandFile)) { $sz = getimagesize($bandFile); if ($sz) $bandH = 210 * $sz[1] / $sz[0]; }
$logo = $ROOT . '/assets/img/companies/scor-logo-white.png';
$wedge = $ROOT . '/assets/img/brochure/wedge.png';
$pin = '<span style="color:#f5b400">&#8226;</span> ';
$foot = '<table width="100%" cellpadding="0" cellspacing="0" dir="ltr" style="background:#0c0d10;border-top:1.2mm solid #f5b400;width:210mm;margin-left:-9mm"><tr>'
      . '<td width="31%" style="padding:3mm 5mm"><img src="' . $logo . '" height="10mm" /><div class="fb">شركة سكور للمعدات الثقيلة والشحن الدولي<br/>SCOR Heavy Equipment &amp; International Shipping</div></td>'
      . '<td class="fc" width="23%">' . $pin . '<b>الإدارة العامة (ألمانيا)</b><br/>004915118316638<br/>004917634389434</td>'
      . '<td class="fc" width="17%">' . $pin . '<b>مكتب حمص</b><br/>00963939062887</td>'
      . '<td class="fc" width="15%">' . $pin . '<b>مكتب درعا</b><br/>00963941435541</td>'
      . '<td class="fc" width="14%">www.scor-sy.de<br/>info@scor-sy.de</td></tr></table>';

$css = '
body { font-family: scorfont; color:#151515; }
.cattitle { font-size:30pt; font-weight:bold; color:#151515; text-align:center; padding:14mm 0 2mm; }
.catsub { font-size:14pt; font-weight:bold; color:#C98F00; text-align:center; padding-bottom:8mm; }
.model { font-size:24pt; font-weight:bold; color:#151515; padding:1mm 0 2.5mm; margin-bottom:3mm; text-align:right; border-bottom:1.1mm solid #F5B400; }
.hero td { vertical-align:top; }
.hero .tx { font-size:10.5pt; line-height:1.85; padding:4mm 5mm; background:#f6f6f6; color:#242424; }
.cols td.col { width:48.5%; vertical-align:top; }
.cols td.gap { width:3%; }
.grp { margin-bottom:3.5mm; }
.gh { background:#151515; color:#fff; font-weight:bold; font-size:11pt; padding:1.5mm 3mm; }
.gh-w { background:#151515; width:9mm; }
.rows td { font-size:10pt; font-weight:bold; padding:1.05mm 2.6mm; border-bottom:0.2mm solid #d9d9d9; }
.rows tr.alt td { background:#e9e9e9; }
.rows td.k { font-weight:bold; width:55%; }
.rows td.v { width:45%; }
.rows td.one { font-weight:bold; }
.ov { width:100%; border-collapse:collapse; margin-top:4mm; }
.ov th { background:#151515; color:#fff; font-weight:bold; font-size:10.5pt; padding:2.2mm 2mm; text-align:right; }
.ov td { font-size:10pt; font-weight:bold; padding:1.9mm 2mm; border-bottom:0.2mm solid #d9d9d9; }
.ov tr.alt td { background:#e9e9e9; }
.contact { font-size:11pt; font-weight:bold; line-height:2.1; }
.contact .h { font-size:13pt; color:#C98F00; }
.nline { font-size:9pt; font-weight:bold; line-height:1.7; }
.fb { color:#f5b400; font-size:7pt; padding-top:1.2mm; }
.fc { color:#fff; font-size:7.3pt; line-height:1.55; padding:3mm 2mm; vertical-align:top; }
.fc b { color:#f5b400; }
';

$cfg = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8', 'format' => 'A4',
    'margin_left' => 9, 'margin_right' => 9, 'margin_top' => (int) round($bandH + 3), 'margin_bottom' => 24,
    'margin_header' => 0, 'margin_footer' => 0,
    'fontDir' => array_merge($cfg['fontDir'], [__DIR__ . '/fonts']),
    'fontdata' => $fontData + ['scorfont' => ['R' => 'Cairo-400.ttf', 'B' => 'Cairo-700.ttf', 'I' => 'Cairo-400.ttf', 'BI' => 'Cairo-700.ttf', 'useOTL' => 0xFF], 'Tajawal' => ['R' => 'Tajawal-400.ttf', 'B' => 'Tajawal-700.ttf', 'I' => 'Tajawal-500.ttf', 'BI' => 'Tajawal-800.ttf']],
    'default_font' => 'scorfont',
    'tempDir' => __DIR__ . '/mpdf/tmp3',
]);
$mpdf->SetDirectionality('rtl');
$mpdf->SetTitle('SCOR — كتالوج الرافعات الشوكية الكهربائية');
$mpdf->SetHTMLHeader($band);
$mpdf->SetHTMLFooter($foot);

/* ---------------- cover + overview --------------------------------------- */
$topModel = end($models);
$html = '<style>' . $css . '</style>'
      . '<div class="cattitle">الرافعات الشوكية الكهربائية</div>'
      . '<div class="catsub">كتالوج المواصفات الفنية الشامل — SCOR</div>';
if ($topModel['hero']) {
    $html .= '<div style="text-align:center"><img src="' . $topModel['hero'] . '" style="width:118mm;max-height:74mm" /></div>';
}
$html .= '<table class="ov"><tr><th>الموديل</th><th>أقصى حمولة</th><th>ارتفاع الرفع</th><th>البطارية</th><th>زمن العمل</th></tr>';
$i = 0;
foreach ($models as $m) {
    $cls = ($i++ % 2) ? ' class="alt"' : '';
    $html .= "<tr$cls><td>SCOR " . htmlspecialchars($m['code']) . '</td><td>' . htmlspecialchars(specVal($m, 'أقصى حمولة')) . '</td><td>'
           . htmlspecialchars(specVal($m, 'ارتفاع الرفع')) . '</td><td>' . htmlspecialchars(specVal($m, 'بطاري')) . '</td><td>'
           . htmlspecialchars(specVal($m, 'زمن العمل')) . '</td></tr>';
}
$html .= '</table>';
$mpdf->WriteHTML($html);

/* ---------------- one page per model -------------------------------------- */
foreach ($models as $m) {
    $h = '<pagebreak /><div class="model">SCOR ' . htmlspecialchars($m['code']) . '</div>';
    if ($m['hero'] || $m['intro']) {
        $photoCell = $m['hero'] ? '<td class="ph" style="width:102mm"><img src="' . $m['hero'] . '" style="width:100mm;max-height:66mm" /></td>' : '';
        $textCell = '<td class="tx">' . htmlspecialchars($m['intro']) . '</td>';
        $h .= '<table class="hero" width="100%" cellpadding="0" cellspacing="0"><tr>' . $photoCell . $textCell . '</tr></table>';
    }
    $h .= columns($m['groups'], $wedge);
    $mpdf->WriteHTML($h);
}

/* ---------------- closing contact page ------------------------------------ */
$mpdf->WriteHTML('<pagebreak />'
    . '<div class="model">تواصل معنا</div>'
    . '<div class="contact">'
    . '<span class="h">الإدارة العامة — ألمانيا</span><br/>'
    . 'SCOR Heavy Machinery Manufacturing<br/>'
    . 'Iltisweg 4, 53842 Troisdorf, Germany<br/>'
    . '004915118316638 — 004917634389434<br/><br/>'
    . '<span class="h">مكتب حمص — سوريا</span><br/>00963939062887<br/><br/>'
    . '<span class="h">مكتب درعا — سوريا</span><br/>00963941435541<br/><br/>'
    . '<span class="h">البريد الإلكتروني والموقع</span><br/>info@scor-sy.de<br/>www.scor-sy.de<br/><br/>'
    . '<span class="h">مناطق التغطية</span><br/>أوروبا — تركيا — العالم العربي — الصين'
    . '</div>'
    . '<div style="height:10mm"></div>'
    . '<div class="nline">• الأبعاد والمواصفات المذكورة تقريبية وقد تختلف حسب نوع الدلو أو الإطارات المستخدمة.</div>'
    . '<div class="nline">• الصور والرسومات لأغراض توضيحية فقط.</div>'
    . '<div class="nline">• تحتفظ شركة SCOR بالحق في إجراء أي تعديلات على المواصفات دون إشعار مسبق.</div>');

while (ob_get_level()) ob_end_clean();
$mpdf->Output('SCOR_Electric_Forklifts_Catalog_AR.pdf', 'D');

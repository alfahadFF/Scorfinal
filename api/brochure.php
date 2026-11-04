<?php
/* SCOR product brochure generator (one.com, mPDF).
   Builds the 2-page A4 short brochure for one model from the model's own
   website page (single source of truth). Exception: electric-forklifts get a
   ONE-page leaflet carrying the full contact footer on that single page:
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
    /* NBSP (U+00A0) -> normal space: source pages join words with &nbsp;,
       which mPDF then treats as one unbreakable block (measured 77mm) and
       that poisons every column-width calculation downstream */
    return ($n && $n->length) ? trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $n->item(0)->textContent)) : '';
}
/* Web pages use paired LRM markers to keep short mixed-direction numbers
   (e.g. 6°–12° and 12#) in source order. mPDF prints those markers as boxes,
   so replace each marked segment with an explicit LTR span instead. */
function hvalue($value) {
    $parts = explode("\u{200E}", (string)$value);
    $out = '';
    foreach ($parts as $i => $part) {
        $part = htmlspecialchars($part);
        $out .= ($i % 2) ? '<span dir="ltr">' . $part . '</span>' : $part;
    }
    return $out;
}
$h1 = xtext($xp, '//h1');
$code = preg_replace('/^SCOR\s+/i', '', $h1);
$code = trim($code) !== '' ? trim($code) : strtoupper($model);
$code = preg_replace('/([A-Za-z])\s+(\d)/', '$1$2', $code);
$display = 'SCOR ' . $code;
$intro = xtext($xp, '(//div[contains(concat(" ",normalize-space(@class)," ")," description ")])[1]');
if (mb_strlen($intro) > 300) {
    $intro = rtrim(mb_substr($intro, 0, 300));
    $intro = preg_replace('/\s+\S*$/', '', $intro) . '…';
}
/* header-band short description: must stay inside the band (<= ~3 lines) */
$headIntro = $intro;
if (mb_strlen($headIntro) > 175) {
    $headIntro = rtrim(mb_substr($headIntro, 0, 175));
    $headIntro = preg_replace('/\s+\S*$/', '', $headIntro) . '…';
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
function isListGroup($g) {
    if (!$g['rows']) return false;
    foreach ($g['rows'] as $r) {
        $v = trim((string)$r[1]);
        if ($v !== '' && $v !== '✓' && $v !== '✔') return false;
    }
    return true;
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
 'ar' => ['brand' => 'للمعدات الثقيلة والشحن الدولي', 'hq' => 'الإدارة العامة (ألمانيا)',
   'homs' => 'مكتب حمص', 'daraa' => 'مكتب درعا', 'photo' => 'المواصفات الرئيسية',
   'notes' => 'ملاحظات', 'legal' => ['الأبعاد والمواصفات المذكورة تقريبية وقد تختلف حسب التجهيزات أو الإطارات المستخدمة.', 'الصور والرسومات لأغراض توضيحية فقط.', 'تحتفظ شركة SCOR بالحق في إجراء أي تعديلات على المواصفات دون إشعار مسبق.']],
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
            $v3 = str_replace('\u{2713}', '<span style="font-family:dejavusans">&#10003;</span>', hvalue($r[1]));
            $h .= "<tr$cls><td class=\"c1 sym\">" . $L . '</td><td class="c2">' . htmlspecialchars($lg[$L] ?? '') . '</td><td class="c3">' . $v3 . '</td></tr>';
        }
        return $h . '</table></td></tr></table>';
    }
    $oneP = true; /* bar+rows as siblings everywhere (mPDF nested-table fix) */
    $bar = '';
    if ($g['title'] !== '') {
        $w = '<td class="gh-w"><img src="' . $wedge . '" width="9mm" /></td>';
        $t = '<td class="gh">' . htmlspecialchars($g['title']) . '</td>';
        $cells = $GLOBALS['rtl'] ? $t . $w : $w . $t;
        $bar = $oneP
            ? '<table class="grpbar" width="100%" cellpadding="0" cellspacing="0"><tr>' . $cells . '</tr></table>'
            : '<tr>' . $cells . '</tr>';
    }
    if (isListGroup($g)) {
        $rows = '<table class="rows" width="100%" cellpadding="0" cellspacing="0">';
        $i = 0;
        foreach ($g['rows'] as $r) {
            $cls = ($i++ % 2) ? ' class="alt"' : '';
            $rows .= "<tr$cls><td class=\"one\">" . htmlspecialchars($r[0]) . '</td></tr>';
        }
        $rows .= '</table>';
        if ($oneP) return '<div class="grp">' . $bar . $rows . '</div>';
        return '<table class="grp" width="100%" cellpadding="0" cellspacing="0">' . $bar . '<tr><td colspan="2">' . $rows . '</td></tr></table>';
    }
    $rows = '<table class="rows" width="100%" cellpadding="0" cellspacing="0">';
    $i = 0;
    foreach ($g['rows'] as $r) {
        $cls = ($i++ % 2) ? ' class="alt"' : '';
        $v = str_replace('\u{2713}', '<span style="font-family:dejavusans">&#10003;</span>', hvalue($r[1]));
        $rows .= "<tr$cls><td class=\"k\">" . htmlspecialchars($r[0]) . '</td><td class="v">' . $v . '</td></tr>';
    }
    $rows .= '</table>';
    /* one-page leaflets: bar and rows as siblings - a rows table nested in a colspan cell makes mPDF mis-size the group bar */
    if ($oneP) return '<div class="grp">' . $bar . $rows . '</div>';
    return '<table class="grp" width="100%" cellpadding="0" cellspacing="0">' . $bar . '<tr><td colspan="2">' . $rows . '</td></tr></table>';
}
function estH($g) {
    $h = 2.4;
    if (isListGroup($g)) {
        $od = $GLOBALS['rtl'] ? 44 : 56;
        foreach ($g['rows'] as $r) $h += 1 + intdiv(mb_strlen($r[0]), $od);
        return $h;
    }
    $kd = $GLOBALS['rtl'] ? 24 : (!empty($GLOBALS['onePage']) ? 19 : 30);
    $vd = $GLOBALS['rtl'] ? 30 : (!empty($GLOBALS['onePage']) ? 26 : 38);
    foreach ($g['rows'] as $r) $h += 1 + intdiv(mb_strlen($r[0]), $kd) + intdiv(mb_strlen($r[1]), $vd);
    return $h;
}
function columns($gs, $wedge) {
    if (!$gs) return '';
    /* For EF40, Turkish EF30, and the expanded PM30/PM50 sheets, select the
       most balanced sequential column break, including row-level splits with
       >=2 rows on either side. Other models' layouts are unchanged. */
    if ((($GLOBALS['model'] ?? '') === 'ef40' && !empty($GLOBALS['onePage']))
        || (($GLOBALS['model'] ?? '') === 'ef30' && ($GLOBALS['lang'] ?? '') === 'tr' && !empty($GLOBALS['onePage']))
        || (($GLOBALS['cat'] ?? '') === 'trailer-concrete-pumps' && in_array(($GLOBALS['model'] ?? ''), ['pump-pm-30', 'pump-pm-50'], true))) {
        $best = null; $bestDiff = INF; $count = count($gs);
        $consider = function ($a, $b) use (&$best, &$bestDiff) {
            if (!$a || !$b) return;
            $ha = 0; $hb = 0;
            foreach ($a as $g) $ha += estH($g);
            foreach ($b as $g) $hb += estH($g);
            $diff = abs($ha - $hb);
            if ($diff < $bestDiff) { $bestDiff = $diff; $best = [$a, $b]; }
        };
        foreach ($gs as $i => $g) {
            if ($i > 0) $consider(array_slice($gs, 0, $i), array_slice($gs, $i));
            $n = count($g['rows']);
            /* PM50 has a longer combined feature list. Let it continue in
               the next column, still one item per row and at least two items
               per part. Standard/optional/notes lists remain untouched. */
            $pm50Features = (($GLOBALS['cat'] ?? '') === 'trailer-concrete-pumps'
                && ($GLOBALS['model'] ?? '') === 'pump-pm-50'
                && in_array($g['title'], ['المميزات', 'Features', 'Merkmale', 'Özellikler'], true));
            if ((!isListGroup($g) || $pm50Features) && !isLetterGroup($g) && $n >= 4) {
                for ($k = 2; $k <= $n - 2; $k++) {
                    $a = array_slice($gs, 0, $i);
                    $a[] = ['title' => $g['title'], 'rows' => array_slice($g['rows'], 0, $k)];
                    $b = [['title' => $g['title'], 'rows' => array_slice($g['rows'], $k)]];
                    $b = array_merge($b, array_slice($gs, $i + 1));
                    $consider($a, $b);
                }
            }
        }
        if ($best !== null) {
            $ca = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $best[0]));
            $cb = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $best[1]));
            return '<table class="cols" width="100%" cellpadding="0" cellspacing="0"><tr>'
                 . '<td class="col">' . $ca . '</td><td class="gap"></td><td class="col">' . $cb . '</td></tr></table>';
        }
    }
    if (count($gs) === 1 && count($gs[0]['rows']) > 12) {
        $half = (int) ceil(count($gs[0]['rows']) / 2);
        $gs = [
            ['title' => $gs[0]['title'], 'rows' => array_slice($gs[0]['rows'], 0, $half)],
            ['title' => '', 'rows' => array_slice($gs[0]['rows'], $half)],
        ];
    }
    $colA = []; $colB = []; $wa = 0; $wb = 0;
    if (!empty($GLOBALS['splitCols'])) {
        /* sequential fill: keep group order, split the group that crosses the
           midpoint — page-layout two columns, balanced by rows if needed */
        $total = 0;
        foreach ($gs as $g) $total += estH($g);
        $target = $total / 2;
        $toB = false;
        foreach ($gs as $g) {
            if ($toB) { $colB[] = $g; continue; }
            $h = estH($g);
            /* EF30's translated groups may be almost perfectly balanced at
               3–5% over the estimate; accept that rather than leaving an
               entire column under-filled. Other model layouts stay unchanged. */
            $tolerance = (!empty($GLOBALS['onePage']) && ($GLOBALS['model'] ?? '') === 'ef30') ? 1.05 : 1.02;
            if (!$colA || $wa + $h <= $target * $tolerance) { $colA[] = $g; $wa += $h; continue; }
            $split = false;
            if (!isListGroup($g) && !isLetterGroup($g) && count($g['rows']) >= 4) {
                $kd = $GLOBALS['rtl'] ? 24 : 19;
                $vd = $GLOBALS['rtl'] ? 30 : 26;
                $rem = $target - $wa; $acc = 2.4; $k = 0;
                foreach ($g['rows'] as $r) {
                    $rh = 1 + intdiv(mb_strlen($r[0]), $kd) + intdiv(mb_strlen($r[1]), $vd);
                    if ($k >= 1 && $acc + $rh > $rem) break;
                    $acc += $rh; $k++;
                }
                if ($k >= 2 && (count($g['rows']) - $k) >= 2) {
                    $colA[] = ['title' => $g['title'], 'rows' => array_slice($g['rows'], 0, $k)];
                    $colB[] = ['title' => $g['title'], 'rows' => array_slice($g['rows'], $k)];
                    $split = true;
                }
            }
            if (!$split) $colB[] = $g;
            $toB = true;
        }
    } else {
        foreach ($gs as $g) {
            if ($wa <= $wb) { $colA[] = $g; $wa += estH($g); }
            else { $colB[] = $g; $wb += estH($g); }
        }
    }
    /* two page columns, each holding whole spec tables (nested): exactly
       98mm per column + 4mm gap inside the 200mm content width. mPDF sizes
       such columns by content unless the text can wrap freely -- with
       NBSP-joined source values normalised at extraction (xtext), the
       49%/2% cell widths are honoured and the columns come out equal. */
    $ca = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $colA));
    $cb = implode('', array_map(function ($g) use ($wedge) { return groupTable($g, $wedge); }, $colB));
    return '<table class="cols" width="100%" cellpadding="0" cellspacing="0"><tr>'
         . '<td class="col">' . $ca . '</td><td class="gap"></td><td class="col">' . $cb . '</td></tr></table>';
}

$customBand = $ROOT . '/assets/img/brochure/header-custom.png';
$bandFile = is_file($customBand) ? $customBand : $stripes;
$bandH = 25;
if (is_file($bandFile)) { $sz = getimagesize($bandFile); if ($sz) $bandH = 210 * $sz[1] / $sz[0]; }
/* model name at the SAME level as the header logo, short description right
   under it — both printed over the band image and kept inside its bounds.
   The title + description block is centred horizontally in the band's white
   area and dropped a few mm so it never hugs the top edge. */
$band = '<img src="' . $bandFile . '" style="width:210mm;margin-left:-5mm" />'
      . '<div style="margin-top:-' . round($bandH - 10, 1) . 'mm;margin-left:88mm;width:116mm;text-align:center">'
      . '<div style="font-size:19pt;font-weight:bold;color:#151515;line-height:1.25">' . htmlspecialchars($display) . '</div>'
      . ($headIntro !== '' ? '<div style="font-size:8.5pt;line-height:1.5;color:#242424;padding-top:1.6mm">' . htmlspecialchars($headIntro) . '</div>' : '')
      . '</div>';
$pin = '<span style="color:#f5b400">&#8226;</span> ';
$footBrand = '<img src="' . $logo . '" height="10mm" /><div class="fb">' . $L['brand'] . '<br/>SCOR Heavy Equipment &amp; International Shipping</div>';
if ($cat === 'electric-forklifts' || ($cat === 'trailer-concrete-pumps' && in_array($model, ['pump-pm-30', 'pump-pm-50'], true))) {
    /* Keep the localized tagline on its own line below the logo. The legacy
       inline image split the first Arabic letter onto the logo line. */
    $footerTagline = $cat === 'electric-forklifts' ? [
        'ar' => 'للمعدات الثقيلة والشحن الدولي',
        'en' => 'Heavy Equipment & International Shipping',
        'de' => 'Baumaschinen & internationaler Versand',
        'tr' => 'Ağır Ekipman ve Uluslararası Nakliye',
    ][$lang] : $L['brand'];
    $footBrand = '<img src="' . $logo . '" height="10mm" /><br/><div class="fb"'
        . ($rtl ? ' dir="rtl" style="text-align:left"' : '') . '>' . htmlspecialchars($footerTagline) . '</div>';
}
$foot = '<table width="100%" cellpadding="0" cellspacing="0" dir="ltr" style="background:#0c0d10;border-top:1.2mm solid #f5b400;width:210mm;margin-left:-5mm"><tr>'
      . '<td width="31%" style="padding:3mm 5mm">' . $footBrand . '</td>'
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
if ($cat === 'electric-forklifts' && $model === 'ef15') {
    $noteRows[] = [
        'ar' => 'يرجى تأكيد التجهيزات والمواصفات النهائية قبل تقديم الطلب.',
        'en' => 'Please confirm final equipment and specifications before ordering.',
        'de' => 'Bitte Ausstattung und endgültige Daten vor der Bestellung bestätigen.',
        'tr' => 'Lütfen siparişten önce nihai donanım ve özellikleri doğrulayın.',
    ][$lang];
}
$anglesG = count($angleRows) >= 2 ? ['title' => $ANGLES, 'rows' => $angleRows] : null;
$notesG = $noteRows ? ['title' => $L['notes'], 'rows' => array_map(function ($n) { return [$n, '']; }, $noteRows)] : null;

/* electric forklifts: ONE-page leaflet — every spec group (incl. dimensions)
   on the single page, no drawing page, and the full contact footer band
   (the one other categories get on their last page) on that only page. */
$onePage = ($cat === 'electric-forklifts');
/* EF40 is requested now; the owner will supply its photograph later. Never
   borrow another model's photograph. The placeholder disappears once the
   proper EF40 hero is installed. */
$photoPending = $onePage && $model === 'ef40' && !$hero;
$photoPendingText = [
    'ar' => 'صورة الموديل ستُضاف لاحقاً', 'en' => 'Model photo coming soon',
    'de' => 'Modellfoto folgt', 'tr' => 'Model fotoğrafı yakında',
][$lang];
/* These updated supplier sheets have more rows; allow an extra balancing
   move while keeping two feature groups beside the first-page photograph. */
$rebalanceUpdatedEF = $onePage && in_array($model, ['ef15', 'ef20', 'ef25', 'ef30', 'ef35', 'ef40'], true);
if ($onePage && $hero && preg_match('/^1\./', basename($hero))) {
    /* white-trimmed crop of the machine photo (assets .../1-crop.jpg):
       the machine fills the frame instead of floating in white space */
    $cropFile = dirname($hero) . '/1-crop.jpg';
    if (is_file($cropFile)) $hero = $cropFile;
}
if ($onePage) {
    /* Regroup the spec rows into FEATURE tables: the EF pages store every
       row in one flat list (plus short summary blocks duplicating some of
       them), but a leaflet must read by feature. Rows are deduplicated by
       key+value and bucketed by localized key patterns; wording untouched. */
    $FEAT_TITLES = [
     'ar' => ['الأداء', 'الأبعاد والأوزان', 'البطارية والنظام الكهربائي', 'المحركات والهيدروليك', 'الهيكل والتوجيه والفرامل'],
     'en' => ['Performance', 'Dimensions & Weights', 'Battery & Electrical System', 'Motors & Hydraulics', 'Chassis, Steering & Brakes'],
     'de' => ['Leistung', 'Abmessungen & Gewichte', 'Batterie & Elektrik', 'Motoren & Hydraulik', 'Fahrwerk, Lenkung & Bremsen'],
     'tr' => ['Performans', 'Boyutlar ve Ağırlıklar', 'Akü ve Elektrik Sistemi', 'Motorlar ve Hidrolik', 'Şasi, Direksiyon ve Frenler'],
    ][$lang];
    $FEAT_RE = [
     'ar' => ['/حمولة|ارتفاع الرفع|زمن العمل|زاوية|ميل السارية|سرعة|الصعود/u', '/طول|العرض|الارتفاع|قطر الدوران|وزن|الوزن|خلوص|مسار|مداس|قاعدة العجلات/u', '/بطاري|الجهد|جهد|الشحن|التحكم|الإشارة/u', '/محرك|محور الإدارة|محور الدفع|أسطوانة الرفع/u'],
     'en' => ['/rated load|max\.?\s*load|lift height|working time|gradeab|climb|angle|mast tilt|speed/i', '/length|width|height|turning radius|(?:vehicle|operating|service) weight|ground clearance|wheel track|wheel tread|wheelbase/i', '/batter|voltage|charging|controller|electricity|indicator/i', '/motor|cylinder|drive axle/i'],
     'de' => ['/nenntragfähigkeit|max\. last|hubhöhe|arbeitszeit|steig|winkel|geschwindigkeit|mastneigung/i', '/länge|breite|höhe|gabellänge|wenderadius|(?:fahrzeug|betriebs|dienst)gewicht|bodenfreiheit|spurweite|radstand/i', '/batterie|akku|spannung|ladezeit|steuerung|anzeige/i', '/motor|hubzylinder|antriebsachse/i'],
     'tr' => ['/nominal yük|maks\. yük|kaldırma yüksekliği|çalışma süresi|tırmanma|eğim|açı|hız/i', '/uzun|genişlik|yükseklik|dönüş yarıçapı|ağırlığı|yerden yükseklik|iz genişliği|dingil mesafesi/i', '/akü|voltaj|şarj|kontrol|gösterge/i', '/motoru|silindiri|tahrik/i'],
    ][$lang];
    $all = []; $seen = [];
    $collect = function ($rows) use (&$all, &$seen) {
        foreach ($rows as $r) {
            $sig = mb_strtolower(trim(preg_replace('/\s+/', ' ', $r[0] . '|' . $r[1])), 'UTF-8');
            if (isset($seen[$sig])) continue;
            $seen[$sig] = 1; $all[] = $r;
        }
    };
    foreach (array_merge($p1, $dims) as $g) $collect($g['rows']);
    $collect($angleRows);
    $buckets = [[], [], [], [], []];
    foreach ($all as $r) {
        $k = mb_strtolower($r[0], 'UTF-8');
        $bi = 4;
        foreach ($FEAT_RE as $i => $re) { if (preg_match($re, $k)) { $bi = $i; break; } }
        $buckets[$bi][] = $r;
    }
    $p1 = [];
    foreach ($buckets as $i => $rows) if ($rows) $p1[] = ['title' => $FEAT_TITLES[$i], 'rows' => $rows];
    /* notes become a full-width strip under the tables, one note per line */
    $notesHtml = '';
    if ($notesG) {
        $items = '';
        foreach ($noteRows as $n) $items .= '<div class="nline1"><span style="color:#C98F00">&#8226;</span> ' . htmlspecialchars($n) . '</div>';
        $w = '<td class="gh-w"><img src="' . $wedge . '" width="9mm" /></td>';
        $tt = '<td class="gh">' . htmlspecialchars($L['notes']) . '</td>';
        $notesHtml = '<table class="grp" width="100%" cellpadding="0" cellspacing="0" style="margin-top:1mm"><tr>' . ($rtl ? $tt . $w : $w . $tt) . '</tr>'
                   . '<tr><td colspan="2" class="nbox1">' . $items . '</td></tr></table>';
    }
    $drawing = '';
}

/* fill-the-page density: estimate column heights and stretch row padding */
function colUnits($gs) {
    if (($GLOBALS['model'] ?? '') === 'ef40' && !empty($GLOBALS['onePage']) && count($gs) === 1 && count($gs[0]['rows']) >= 4) {
        $g = $gs[0]; $n = count($g['rows']); $bestK = 2; $bestDiff = INF;
        for ($k = 2; $k <= $n - 2; $k++) {
            $a = ['title' => $g['title'], 'rows' => array_slice($g['rows'], 0, $k)];
            $b = ['title' => $g['title'], 'rows' => array_slice($g['rows'], $k)];
            $diff = abs(estH($a) - estH($b));
            if ($diff < $bestDiff) { $bestDiff = $diff; $bestK = $k; }
        }
        $gs = [
            ['title' => $g['title'], 'rows' => array_slice($g['rows'], 0, $bestK)],
            ['title' => $g['title'], 'rows' => array_slice($g['rows'], $bestK)],
        ];
    } elseif (count($gs) === 1 && count($gs[0]['rows']) > 12) {
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
$heroCap = 100;
$BODYH = 297 - (int) round($bandH + 3) - 24;
$heroW = 150;
$heroImgH = 0;
if ($hero) { $hs = getimagesize($hero); if ($hs) $heroImgH = min($heroCap, $heroW * $hs[1] / $hs[0]); }
$topH = $hero ? $heroImgH + 4 : ($photoPending ? 42 : 0);
$avail1 = $BODYH - 10 - $topH;
$units1 = colUnits($p1);
$pad1 = 0.8;
foreach ([1.4, 1.2, 1.05, 0.9, 0.8] as $cand) {
    if ($units1 * (3.4 + 2 * $cand) <= $avail1 * 0.94) { $pad1 = $cand; break; }
}
/* page-1 -> page-2 group moves happen in the render loop, on real overflow */
$moved = [];
if ($onePage) {
    $p2groups = array_reverse($moved);
} else {
    $p2groups = array_merge($dims, array_reverse($moved), $anglesG ? [$anglesG] : [], $notesG ? [$notesG] : []);
}
/* PM30 has a full supplied feature list plus retained earlier data.
   Defer the earlier mechanical group and both lists before measurement to
   keep the photograph large and avoid mPDF shrinking first-page text below
   the approved 10pt. Other models' brochures are not changed. */
if ($cat === 'trailer-concrete-pumps' && $model === 'pump-pm-30') {
    $deferTitles = [
        'ar' => ['البيانات الميكانيكية (بيانات سابقة)', 'المميزات', 'مميزات إضافية (بيانات سابقة)'],
        'en' => ['Mechanical Data (Previous Data)', 'Features', 'Additional Features (Previous Data)'],
        'de' => ['Mechanische Daten (frühere Angaben)', 'Merkmale', 'Weitere Merkmale (frühere Angaben)'],
        'tr' => ['Mekanik Veriler (Önceki Bilgiler)', 'Özellikler', 'Ek Özellikler (Önceki Bilgiler)'],
    ][$lang];
    $kept = []; $deferred = [];
    foreach ($p1 as $g) {
        if (in_array($g['title'], $deferTitles, true)) $deferred[] = $g;
        else $kept[] = $g;
    }
    if (count($deferred) === 3) {
        $p1 = $kept;
        array_splice($p2groups, count($dims), 0, $deferred);
    }
}
/* The updated PM50 combines the supplied features with the retained ones.
   Move its mechanical data and feature list to page 2 so the large photo and
   the approved 10pt/98mm layout remain intact on the first page. */
if ($cat === 'trailer-concrete-pumps' && $model === 'pump-pm-50') {
    $deferTitles = [
        'ar' => ['البيانات الميكانيكية', 'المميزات'],
        'en' => ['Mechanical Data', 'Features'],
        'de' => ['Mechanische Daten', 'Merkmale'],
        'tr' => ['Mekanik Veriler', 'Özellikler'],
    ][$lang];
    $kept = []; $deferred = [];
    foreach ($p1 as $g) {
        if (in_array($g['title'], $deferTitles, true)) $deferred[] = $g;
        else $kept[] = $g;
    }
    if (count($deferred) === 2) {
        $p1 = $kept;
        array_splice($p2groups, count($dims), 0, $deferred);
    }
}
$drawCap = 84; $drawH = 0;
if ($drawing) { $ds = getimagesize($drawing); if ($ds) $drawH = min($drawCap, 190 * $ds[1] / $ds[0]) + 4; }
$notesH = $noteLines || true ? 0 : 0;
$avail2 = $BODYH - 15 - $drawH;
$units2 = colUnits($p2groups);
$pad2 = 0.8;
foreach ([1.4, 1.2, 1.05, 0.9, 0.8] as $cand) {
    if ($units2 * (3.4 + 2 * $cand) + 34 <= $avail2 * 0.94) { $pad2 = $cand; break; }
}
if ($drawing && $units2 * (3.4 + 2 * $pad2) + 34 <= $avail2 - 40) $drawCap = 98;

/* model name + short description live in the header band (see $band) */

$css = '
body { font-family: ' . $font . '; color:#151515; }
.model { font-size:22pt; font-weight:bold; color:#4a4a4a; padding:1mm 0 2mm; ' . ($rtl ? 'text-align:right;' : '') . ' }
.hero td { vertical-align:top; }
.hero .tx { font-size:10.5pt; font-weight:bold; line-height:1.9; padding:1mm 4mm; }
.cols td.col { width:49%; vertical-align:top; }
.cols td.gap { width:2%; }
.grp { margin-bottom:3.5mm; }
.gh { background:#151515; color:#fff; font-weight:bold; font-size:11pt; padding:1.5mm 3mm; }
.gh-w { background:#151515; width:9mm; }
.rows td { font-size:10pt; font-weight:bold; padding:1.0mm 2.6mm; border-bottom:0.2mm solid #d9d9d9; }
.rows tr.alt td { background:#e9e9e9; }
.rows td.k { font-weight:bold; width:55%; }
.rows td.v { width:45%; }
.rows td.one { font-weight:bold; }
.draw { text-align:center; padding:2mm 0 4mm; }
.rows3 td.c1 { width:11%; text-align:center; }
.rows3 td.c2 { width:57%; }
.rows3 td.c3 { width:32%; font-weight:bold; }
.rows3 td.sym { font-weight:bold; font-size:11pt; }
.rows3 tr.ch td { background:#3a3a3a; color:#fff; font-weight:bold; font-size:9pt; }
.nbox { background:#f2f2f2; padding:2.5mm 3mm; }
.nline { font-size:9pt; font-weight:bold; line-height:1.7; }
.nline1 { font-size:9pt; line-height:1.55; padding:0.5mm 0; }
.nbox1 { background:#f6f6f6; padding:1.8mm 3mm; }
.hero .tx1 { background:#f6f6f6; font-weight:normal; color:#242424; line-height:1.75; padding:3mm 4.5mm; }
.fb { color:#f5b400; font-size:7pt; padding-top:1.2mm; }
.fc { color:#fff; font-size:7.3pt; line-height:1.55; padding:3mm 2mm; vertical-align:top; }
.fc b { color:#f5b400; }
';

/* ---------------- render (closed-loop: measure real page count, adjust until exactly 2 pages) */
$cfg = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
$customFoot = $ROOT . '/assets/img/brochure/footer-custom.png';
$footBand = is_file($customFoot) ? $customFoot : $ROOT . '/assets/img/brochure/footer-band.png';
$foot1 = '<img src="' . $footBand . '" style="width:210mm;margin-left:-5mm" />';
$smallFont = false;
$tinyFont = false;
$loopMoved = 0;
$mpdf = null;
$phase = 'fit';
$splitCols = true; /* two-column page layout with row-level balancing from the start */
$ok1 = $pad1; $ok2 = $pad2; $okHero = $heroCap;
$tries = 0;
while ($tries++ < 48) {
    $hasP2 = $drawing !== '' || count($p2groups) > 0;
    /* While EF40 awaits its photo, its two-page language versions can use
       more breathing room between rows rather than leave sparse lower pages. */
    $maxPad = ($photoPending && $hasP2) ? 3.4 : 1.9;
    /* The revised forklift sheets and PM30/50 can use more of page two
       without affecting the first-page photograph or the 10pt table text. */
    $maxPad2 = (($cat === 'trailer-concrete-pumps' && $model === 'pump-pm-50')
        || ($cat === 'electric-forklifts' && in_array($model, ['ef15', 'ef20', 'ef25', 'ef30', 'ef35', 'ef40'], true) && $hero)) ? 5.2
        : (($cat === 'trailer-concrete-pumps' && $model === 'pump-pm-30') ? 3.4 : $maxPad);
    $p1html = '';
    if ($hero) $p1html .= '<div style="text-align:center;padding:0 0 3mm"><img src="' . $hero . '" style="width:' . $heroW . 'mm;max-height:' . $heroCap . 'mm" /></div>';
    elseif ($photoPending) $p1html .= '<div style="margin:0 25mm 4mm;padding:12mm 3mm;text-align:center;background:#FFFFFF;border:0.3mm solid #667085;color:#667085;font-size:11pt">' . htmlspecialchars($photoPendingText) . '</div>';
    $p1html .= columns($p1, $wedge);
    if ($onePage && !$hasP2 && !empty($notesHtml)) $p1html .= $notesHtml;
    $p2html = '';
    if ($drawing) $p2html .= '<div class="draw"><img src="' . $drawing . '" style="max-height:' . $drawCap . 'mm;max-width:100%" /></div>';
    $p2html .= columns($p2groups, $wedge);
    if ($onePage && $hasP2 && !empty($notesHtml)) $p2html .= $notesHtml;

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4',
        'margin_left' => 5, 'margin_right' => 5, 'margin_top' => (int) round($bandH + 3), 'margin_bottom' => 24,
        'margin_header' => 0, 'margin_footer' => 0,
        'fontDir' => array_merge($cfg['fontDir'], [__DIR__ . '/fonts']),
        'fontdata' => $fontData + ['scorfont' => ['R' => 'Cairo-400.ttf', 'B' => 'Cairo-700.ttf', 'I' => 'Cairo-400.ttf', 'BI' => 'Cairo-700.ttf', 'useOTL' => 0xFF], 'Tajawal' => ['R' => 'Tajawal-400.ttf', 'B' => 'Tajawal-700.ttf', 'I' => 'Tajawal-500.ttf', 'BI' => 'Tajawal-800.ttf']],
        'default_font' => $font,
        'tempDir' => __DIR__ . '/mpdf/tmp3',
    ]);
    if ($rtl) $mpdf->SetDirectionality('rtl');
    $mpdf->SetTitle($display . ' — SCOR Brochure');
    $mpdf->SetHTMLHeader($band);
    $mpdf->SetHTMLFooter($hasP2 ? $foot1 : $foot);
    $fsx = $smallFont ? '.rows td{font-size:9.4pt}' : '';
    if ($tinyFont) $fsx = '.rows td{font-size:9pt;line-height:1.22}';
    $dens1 = '.rows td{padding:' . $pad1 . 'mm 2.6mm}.grp{margin-bottom:' . (3 + ($pad1 - 1) * 3) . 'mm}' . $fsx;
    $dens2 = '.rows td{padding:' . $pad2 . 'mm 2.6mm}.grp{margin-bottom:' . (3 + ($pad2 - 1) * 3) . 'mm}' . $fsx;
    try {
        $mpdf->WriteHTML('<style>' . $css . $dens1 . '</style>' . $p1html . ($hasP2 ? '<pagebreak />' : ''));
        if (!$hasP2) { $over1 = $mpdf->page > 1; $over2 = false; }
        else {
            $over1 = $mpdf->page > 2; $over2 = false;
            if (!$over1) {
                $mpdf->SetHTMLFooter($foot);
                $mpdf->WriteHTML('<style>' . $dens2 . '</style>' . $p2html);
                $over2 = $mpdf->page > 2;
            }
        }
    } catch (Throwable $e) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        exit('Brochure generation failed.');
    }
    if ($over1 || $over2) {
        if ($phase === 'pd') {
            /* push-down overshot page 2: take the group back to page 1 */
            $idx = $onePage ? 0 : count($dims);
            $g = $p2groups[$idx]; array_splice($p2groups, $idx, 1);
            $p1[] = $g;
            if (!$onePage) $loopMoved--;
            $noPD = true; $phase = 'fit'; continue;
        }
        if ($phase === 'pb') {
            /* pull-back overshot: put the group back on page 2 and stop pulling */
            $g = array_pop($p1);
            array_splice($p2groups, $onePage ? 0 : count($dims), 0, [$g]);
            if (!$onePage) $loopMoved++;
            $noPB = true; $phase = 'fit'; continue;
        }
        if ($phase === 'fit') {
            if ($over1) {
                /* Before moving whole EF30 groups to a sparse second page,
                   try slightly tighter row padding, without shrinking the
                   10pt text or the 150mm hero photo. */
                if ($onePage && $model === 'ef30' && !$p2groups && $pad1 > 0.50) { $pad1 = 0.50; }
                elseif (count($p1) > 1) {
                    $g = array_pop($p1);
                    array_splice($p2groups, ($onePage ? 0 : count($dims)) + $loopMoved, 0, [$g]);
                    $loopMoved++;
                } elseif ($pad1 > 0.62) $pad1 = max(0.62, round($pad1 - 0.13, 2));
                elseif ($heroCap > 30) $heroCap -= 8;
                elseif (!$smallFont) $smallFont = true;
                elseif (!$tinyFont) $tinyFont = true;
                elseif (empty($splitCols)) $splitCols = true;
                else break;
            } else {
                if ($drawing && $drawCap > 36) $drawCap -= 14;
                elseif ($pad2 > 0.62) $pad2 = max(0.62, round($pad2 - 0.13, 2));
                else {
                    $big = -1; $bigH = 0;
                    foreach ($p2groups as $gi => $g) {
                        if (count($g['rows']) > 6 && estH($g) > $bigH) { $bigH = estH($g); $big = $gi; }
                    }
                    if ($big >= 0) {
                        $half = (int) ceil(count($p2groups[$big]['rows']) / 2);
                        $second = ['title' => $p2groups[$big]['title'], 'rows' => array_slice($p2groups[$big]['rows'], $half)];
                        $p2groups[$big]['rows'] = array_slice($p2groups[$big]['rows'], 0, $half);
                        array_splice($p2groups, $big + 1, 0, [$second]);
                    } elseif (!$smallFont) $smallFont = true;
                    elseif (!$tinyFont) $tinyFont = true;
                    else break;
                }
            }
            continue;
        }
        /* stretch overshot: restore the best-known settings and finish */
        $pad1 = $ok1; $pad2 = $ok2; $heroCap = $okHero; $phase = 'done'; continue;
    }
    /* success: one page (no page-2 content) or exactly two pages */
    if ($phase === 'pb') { $phase = 'fit'; continue; }
    if ($phase === 'pd') { $noPD = !$rebalanceUpdatedEF; $noPB = true; $phase = 'fit'; continue; }
    if ($phase === 'fit') {
        /* rebalance: pull page-2 groups back onto page 1 while they fit, so
           neither page is left mostly empty */
        if (empty($noPB)) {
            $mvAvail = $onePage ? count($p2groups) : $loopMoved;
            if ($mvAvail > 0) {
                $mvIdx = $onePage ? 0 : count($dims);
                $g = array_splice($p2groups, $mvIdx, 1)[0];
                $p1[] = $g;
                if (!$onePage) $loopMoved--;
                $phase = 'pb';
                continue;
            }
            $noPB = true;
        }
        /* if page 2 is still mostly empty, push page 1's last group down so
           both pages carry a fair share */
        /* EF40's pending-photo draft has long sourced descriptions: retain
           at least three groups on page 1 to avoid two sparse pages. */
        $minP1Groups = ($onePage && $model === 'ef40') ? 3 : ($rebalanceUpdatedEF ? 2 : 1);
        if (empty($noPD) && $hasP2 && count($p1) > $minP1Groups) {
            $estP2 = colUnits($p2groups) * (3.4 + 2 * $pad2) + (isset($drawH) ? $drawH : 0);
            if ($estP2 < $avail2 * ($rebalanceUpdatedEF ? 0.72 : 0.5)) {
                $g = array_pop($p1);
                array_splice($p2groups, $onePage ? 0 : count($dims), 0, [$g]);
                if (!$onePage) $loopMoved++;
                $phase = 'pd';
                continue;
            }
            $noPD = true;
        }
        $ok1 = $pad1; $ok2 = $pad2; $okHero = $heroCap; $phase = 's0';
        if ($heroCap < 100) { $heroCap = min(100, $heroCap + 6); continue; }
        $phase = 's1';
        if ($pad1 < $maxPad) { $pad1 = min($maxPad, round($pad1 + 0.3, 2)); continue; }
        if ($hasP2 && $pad2 < $maxPad2) { $pad2 = min($maxPad2, round($pad2 + 0.3, 2)); $phase = 's2'; continue; }
        break;
    }
    if ($phase === 's0') {
        $okHero = $heroCap;
        if ($heroCap < 100) { $heroCap = min(100, $heroCap + 6); continue; }
        $phase = 's1';
        if ($pad1 < $maxPad) { $pad1 = min($maxPad, round($pad1 + 0.3, 2)); continue; }
        break;
    }
    if ($phase === 's1') {
        $ok1 = $pad1;
        if ($pad1 < $maxPad) { $pad1 = min($maxPad, round($pad1 + 0.3, 2)); continue; }
        if ($hasP2 && $pad2 < $maxPad2) { $pad2 = min($maxPad2, round($pad2 + 0.3, 2)); $phase = 's2'; continue; }
        break;
    }
    if ($phase === 's2') {
        $ok2 = $pad2;
        if ($pad2 < $maxPad2) { $pad2 = min($maxPad2, round($pad2 + 0.3, 2)); continue; }
        break;
    }
    break;
}
$file = 'SCOR_' . preg_replace('/\s+/', '', $code) . '_Brochure_' . strtoupper($lang) . '.pdf';
while (ob_get_level()) ob_end_clean();
$mpdf->Output($file, 'D');

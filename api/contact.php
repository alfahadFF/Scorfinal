<?php
/* SCOR website contact form handler (hosting: one.com, PHP mail).
   Receives the site-wide "Let Us Reach You" form (all languages) and
   emails the submission to the official inbox. */

$TO   = 'info@scor-sy.de';
$FROM = 'info@scor-sy.de';

function scor_is_ajax() {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    return isset($_SERVER['HTTP_ACCEPT']) &&
           strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}

function scor_respond($ok, $code = 200) {
    if (scor_is_ajax()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => $ok));
    } else {
        $back = '/';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            if ($host === '' || parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $host) {
                $back = $_SERVER['HTTP_REFERER'];
            }
        }
        header('Location: ' . $back);
    }
    exit;
}

function scor_field($k) {
    return isset($_POST[$k]) ? trim((string) $_POST[$k]) : '';
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    scor_respond(false, 405);
}

// Honeypot: bots fill it, humans never see it. Pretend success, send nothing.
if (!empty($_POST['bot-field'])) {
    scor_respond(true);
}

$name    = scor_field('fullName');
$phone   = scor_field('phone');
$email   = scor_field('email');
$country = scor_field('country');
$city    = scor_field('city');
$message = scor_field('message');
$ref     = scor_field('ref');
$product = scor_field('product_id');
$agree   = isset($_POST['aggreement']);

if ($name === '' || $phone === '' || $country === '' || $city === '' || !$agree) {
    scor_respond(false, 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    scor_respond(false, 422);
}

$types = array('1' => 'Complaint', '2' => 'Information request', '3' => 'Suggestion', '4' => 'Appreciation', '5' => 'Others');
$cats  = array('1' => 'Product', '2' => 'Sales', '3' => 'Service', '4' => 'Spare parts', '5' => 'Human resources', '6' => 'Others');
$type = isset($types[scor_field('type_primary')]) ? $types[scor_field('type_primary')] : '';
$cat  = isset($cats[scor_field('type_secondary')]) ? $cats[scor_field('type_secondary')] : '';

$lang = '';
if ($ref !== '' && preg_match('#/(en|ar|de|tr)/#', $ref, $m)) {
    $lang = strtoupper($m[1]);
}

$safeName = str_replace(array("\r", "\n"), ' ', $name);
$subject  = 'Website contact form' . ($type !== '' ? ' - ' . $type : '') . ' - ' . $safeName;
if (preg_match('/[^\x20-\x7E]/', $subject)) {
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

$lines   = array();
$lines[] = 'New message from the SCOR website contact form';
$lines[] = '----------------------------------------';
$lines[] = 'Name: ' . $name;
$lines[] = 'Phone: ' . $phone;
if ($email !== '')   { $lines[] = 'Email: ' . $email; }
$lines[] = 'Country: ' . $country;
$lines[] = 'City: ' . $city;
if ($type !== '')    { $lines[] = 'Subject type: ' . $type; }
if ($cat !== '')     { $lines[] = 'Subject category: ' . $cat; }
if ($product !== '') { $lines[] = 'Product ID: ' . $product; }
if ($lang !== '')    { $lines[] = 'Website language: ' . $lang; }
if ($ref !== '')     { $lines[] = 'Sent from page: ' . $ref; }
$lines[] = '';
$lines[] = 'Message:';
$lines[] = $message !== '' ? $message : '(no message)';

$headers   = array();
$headers[] = 'From: SCOR Website <' . $FROM . '>';
if ($email !== '') {
    $headers[] = 'Reply-To: ' . str_replace(array("\r", "\n"), '', $email);
}
$headers[] = 'Content-Type: text/plain; charset=utf-8';
$headers[] = 'X-Mailer: SCOR contact form';

$ok = mail($TO, $subject, implode("\n", $lines), implode("\r\n", $headers));
scor_respond($ok, $ok ? 200 : 500);

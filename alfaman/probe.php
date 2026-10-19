<?php
/* ALFAMAN hosting probe — upload next to api.php + config.php, open in the
   browser once, then DELETE it. It prints no passwords, only yes/no facts:
   PHP version, which PDO drivers exist, and whether the database in
   config.php is reachable from THIS hosting (the one.com test).
   Expected runtime: a few seconds (connection attempts may time out). */
header('Content-Type: text/plain; charset=utf-8');
echo "PHP: ", PHP_VERSION, "\n";
echo "PDO drivers: ", implode(', ', PDO::getAvailableDrivers()), "\n";
echo "pdo_pgsql present: ", in_array('pgsql', PDO::getAvailableDrivers(), true) ? "YES" : "NO", "\n";
echo "pdo_mysql present: ", in_array('mysql', PDO::getAvailableDrivers(), true) ? "YES" : "NO", "\n";
echo "gd (image compression): ", extension_loaded('gd') ? "YES" : "NO", "\n";
$cfgFile = __DIR__ . '/config.php';
if (!file_exists($cfgFile)) { echo "config.php: MISSING — nothing to test.\n"; exit; }
$cfg = require $cfgFile;
echo "config driver: {$cfg['driver']}\n";
try {
    if ($cfg['driver'] === 'pgsql') {
        $pdo = new PDO("pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};connect_timeout=8", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } elseif ($cfg['driver'] === 'mysql') {
        $pdo = new PDO("mysql:host={$cfg['host']};port=" . ($cfg['port'] ?? 3306) . ";dbname={$cfg['name']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } else {
        echo "sqlite driver — no network test needed.\n"; exit;
    }
    $n = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo "DATABASE CONNECTION: OK (products table reachable, $n products)\n";
} catch (Throwable $e) {
    echo "DATABASE CONNECTION: FAILED — ", substr($e->getMessage(), 0, 160), "\n";
    echo "(If this says 'driver not found', pdo_pgsql is not installed here.\n";
    echo " If it times out / connection refused, the hosting firewall blocks it.\n";
    echo " Either way the MariaDB path in the README applies.)\n";
}

<?php
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
header('Content-Type: text/plain');

echo "PHP version: " . PHP_VERSION . "\n\n";
echo "-- file check --\n";
$base = __DIR__;
foreach (['config.php','mailer.php','claim_mailer.php','claim_view.php','claim_item.php','respond_verification.php','email_diag.php'] as $f) {
    $p = $base . '/' . $f;
    printf("%-24s exists=%s  bytes=%d\n", $f, file_exists($p) ? 'yes' : 'NO ', file_exists($p) ? filesize($p) : 0);
}

echo "\n-- loading files (any error below points to the broken file) --\n";
require_once $base . "/config.php";        echo "config.php      loaded OK\n";
require_once $base . "/mailer.php";        echo "mailer.php      loaded OK\n";
require_once $base . "/claim_mailer.php";  echo "claim_mailer.php loaded OK\n";

echo "\n-- functions --\n";
echo "send_email defined        : " . (function_exists('send_email') ? 'YES' : 'NO') . "\n";
echo "claim_status_email defined: " . (function_exists('claim_status_email') ? 'YES' : 'NO') . "\n";

echo "\nIf both say YES, the mailer is wired correctly.\n";
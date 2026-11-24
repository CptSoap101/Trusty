<?php
// Opens the app for logged-in users; everyone else goes to the login page.
declare(strict_types=1);
require __DIR__ . '/lib.php';

start_session();
if (!users_exist()) { header('Location: setup.php'); exit; }
$user = current_user();
if (!$user) { header('Location: login.php'); exit; }

$boot = json_encode([
    'me' => $user,
    'csrf' => $_SESSION['csrf'],
    'api' => 'api.php',
    'fileUrl' => 'api.php?action=file&id=',
    'logoutUrl' => 'logout.php',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link rel="manifest" href="manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<style>:root{color-scheme:light;box-sizing:border-box;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}html{scroll-padding-top:env(safe-area-inset-top,0px)}body{margin:0;padding:0;font:14px -apple-system,BlinkMacSystemFont,sans-serif;background:#fff;color:#000}img{max-width:100%}[hidden]{display:none!important}</style>
<script>window.TRUSTY = <?= $boot ?>;</script>
<script src="shim.js?v=1"></script>
</head>
<body>
<?php readfile(__DIR__ . '/app.html'); ?>
</body>
</html>

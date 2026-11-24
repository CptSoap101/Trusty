<?php
// Shared look for the login and setup pages.
function auth_page(string $title, string $body): void {
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title) ?> · Trusty Ofisina</title>
<link rel="icon" type="image/png" href="assets/icon-192.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#121418">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<style>
:root{--ink:#0C1220;--muted:#525C6E;--line:#E1E6EE;--blue1:#1D4FA0;--blue2:#2F74D0;--black:#121418;--bad:#D0342C;--bad-soft:#FDE8E6;--good:#11845A;--good-soft:#E4F6EE}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:#F3F6FA;color:var(--ink);font-family:Nunito,ui-rounded,system-ui,sans-serif;font-size:16px;line-height:1.5}
body{display:grid;place-items:center;min-height:100vh;padding:24px max(16px,env(safe-area-inset-right)) 24px max(16px,env(safe-area-inset-left));
  background:radial-gradient(60% 50% at 100% 0%,rgba(47,116,208,.12),transparent),radial-gradient(50% 40% at 0% 100%,rgba(18,20,24,.08),transparent),#F3F6FA}
.box{width:min(420px,100%);background:#fff;border:1px solid var(--line);border-radius:28px;padding:32px 28px;box-shadow:0 2px 4px rgba(16,24,40,.05),0 24px 60px -20px rgba(16,24,40,.3);position:relative;overflow:hidden}
.box::after{content:"";position:absolute;left:0;right:0;bottom:0;height:10px;background:#2A2C31 repeating-linear-gradient(90deg,#FFD400 0 18px,transparent 18px 34px) 0 50%/34px 2px repeat-x}
.logo{display:block;height:84px;margin:0 auto 8px}
h1{font-size:26px;font-weight:900;letter-spacing:-.01em;text-align:center;margin:0 0 4px}
p.sub{color:var(--muted);text-align:center;margin:0 0 24px}
label{display:block;font-weight:800;font-size:14px;margin:14px 0 6px}
input,select{width:100%;font:inherit;border:1px solid #D7DFEA;border-radius:14px;padding:12px 14px;background:#fff;color:var(--ink);min-height:48px}
input:focus,select:focus{outline:none;border-color:var(--blue2);box-shadow:0 0 0 3px #E2EDFB}
button{width:100%;margin-top:22px;min-height:50px;border:0;border-radius:999px;background:linear-gradient(135deg,var(--blue1),var(--blue2));color:#fff;font:inherit;font-weight:800;font-size:16px;cursor:pointer;box-shadow:0 10px 24px -10px rgba(47,116,208,.7)}
button:hover{filter:brightness(1.06)}
button:focus-visible{outline:3px solid #9CC2F5;outline-offset:2px}
.err{background:var(--bad-soft);color:var(--bad);border-radius:14px;padding:12px 14px;font-weight:700;margin-bottom:8px}
.ok{background:var(--good-soft);color:var(--good);border-radius:14px;padding:12px 14px;font-weight:700;margin-bottom:8px}
.hint{color:var(--muted);font-size:13px;margin-top:6px}
.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media (max-width:420px){.two{grid-template-columns:1fr}}
a{color:var(--blue2);font-weight:800}
</style>
</head>
<body><main class="box"><img class="logo" src="assets/trusty-logo.png" alt="Trusty"><?= $body ?></main></body>
</html><?php
    exit;
}

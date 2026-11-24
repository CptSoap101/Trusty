<?php
// One-time setup: creates the database tables and the two logins.
// It refuses to run once logins exist, so it can't be used to take over the app.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/auth_page.php';

start_session();

// Step 1: database details (only while config.php doesn't exist yet).
if ($CONFIG === null) {
    $error = '';
    $v = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => ''];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
        $pass = (string)($_POST['db_pass'] ?? '');
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
            $error = 'The page expired. Try again.';
        } elseif ($v['db_name'] === '' || $v['db_user'] === '') {
            $error = 'Fill in the database name and user.';
        } else {
            try {
                new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $v['db_host'], $v['db_name']), $v['db_user'], $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $cfg = ['db_host' => $v['db_host'], 'db_name' => $v['db_name'], 'db_user' => $v['db_user'], 'db_pass' => $pass, 'upload_max_mb' => 20];
                $php = "<?php\n// Created by setup.php. Keep this file private.\nreturn " . var_export($cfg, true) . ";\n";
                if (@file_put_contents(__DIR__ . '/config.php', $php, LOCK_EX) === false) {
                    $error = 'Connected, but config.php could not be written. Check the folder permissions in File Manager.';
                } else {
                    @chmod(__DIR__ . '/config.php', 0640);
                    header('Location: setup.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Can\'t connect with these details. Check them in hPanel → Databases → MySQL Databases.';
            }
        }
    }
    auth_page('Setup', '
<h1>Connect the database</h1><p class="sub">Step 1 of 2 · Copy these from hPanel → Databases → MySQL Databases</p>
' . ($error ? '<div class="err" role="alert">' . e($error) . '</div>' : '') . '
<form method="post" autocomplete="off">
  <input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">
  <label for="h">Database host</label><input id="h" name="db_host" value="' . e($v['db_host']) . '" required>
  <p class="hint">On Hostinger this is almost always <b>localhost</b>.</p>
  <label for="n">Database name</label><input id="n" name="db_name" value="' . e($v['db_name']) . '" placeholder="u123456789_trusty" required>
  <label for="us">Database user</label><input id="us" name="db_user" value="' . e($v['db_user']) . '" placeholder="u123456789_trusty" required>
  <label for="pw">Database password</label><input id="pw" name="db_pass" type="password" required>
  <button type="submit">Connect</button>
</form>');
}

try { db(); }
catch (PDOException $e) {
    auth_page('Setup', '<h1>Setup</h1><div class="err">Can\'t connect to the database. Check the database name, user and password in config.php.</div><p class="hint">Hostinger: hPanel → Databases → MySQL Databases shows the exact names (they start with u…_).</p>');
}

if (users_exist()) {
    auth_page('Setup', '<h1>Already set up</h1><div class="ok">Trusty Ofisina is ready.</div><p class="sub">For safety, delete <b>setup.php</b> from File Manager now.</p><p style="text-align:center"><a href="login.php">Go to log in</a></p>');
}

$error = '';
$names = ['Raichel', 'Raidjir'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'The page expired. Try again.';
    } else {
        $pw = [];
        foreach ($names as $i => $n) {
            $a = (string)($_POST["p$i"] ?? '');
            $b = (string)($_POST["c$i"] ?? '');
            if (strlen($a) < 10) { $error = "Password for $n must be at least 10 characters."; break; }
            if ($a !== $b) { $error = "The two passwords for $n don't match."; break; }
            $pw[$n] = $a;
        }
        if (!$error) {
            create_tables();
            $st = db()->prepare('INSERT INTO users (username, pass_hash, created_at) VALUES (?, ?, ?)');
            foreach ($pw as $n => $p) $st->execute([$n, password_hash($p, PASSWORD_DEFAULT), is_sqlite() ? gmdate('c') : gmdate('Y-m-d H:i:s')]);
            @file_put_contents(data_dir() . '/uploads/probe.txt', 'probe');
            auth_page('Setup', '<h1>All set</h1><div class="ok">The database and both logins are ready.</div>
<div class="err" id="probe" hidden>Warning: uploaded photos can be opened without logging in. Make sure the <b>.htaccess</b> files inside the main folder and the <b>data</b> folder were uploaded (File Manager → Settings → Show hidden files).</div>
<p class="sub">Now delete <b>setup.php</b> in File Manager, then log in.</p><p style="text-align:center"><a href="login.php">Go to log in</a></p>
<script>fetch("data/uploads/probe.txt",{cache:"no-store"}).then(function(r){if(r.ok)document.getElementById("probe").hidden=false;});</script>');
        }
    }
}

$fields = '';
foreach ($names as $i => $n) {
    $fields .= '<label for="p' . $i . '">Password for ' . e($n) . '</label><div class="two">
      <input id="p' . $i . '" name="p' . $i . '" type="password" autocomplete="new-password" minlength="10" required placeholder="New password">
      <input name="c' . $i . '" type="password" autocomplete="new-password" minlength="10" required placeholder="Repeat it" aria-label="Repeat password for ' . e($n) . '"></div>';
}
auth_page('Setup', '
<h1>Create the logins</h1><p class="sub">Step 2 of 2 · Passwords for Raichel and Raidjir. This runs once.</p>
' . ($error ? '<div class="err" role="alert">' . e($error) . '</div>' : '') . '
<form method="post">
  <input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">' . $fields . '
  <p class="hint">At least 10 characters. Use a different password for each person.</p>
  <button type="submit">Create database and logins</button>
</form>');

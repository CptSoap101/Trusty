<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/auth_page.php';

start_session();
if (!users_exist()) { header('Location: setup.php'); exit; }
if (current_user()) { header('Location: ./'); exit; }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    $name = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $error = 'The page expired. Try again.';
    } elseif (too_many_attempts()) {
        $error = 'Too many wrong attempts. Wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT username, pass_hash FROM users WHERE username = ?');
        $st->execute([$name]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['pass_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $u['username'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ./');
            exit;
        }
        record_failed_attempt();
        $error = 'That name and password don\'t match. Try again.';
    }
}

$names = db()->query('SELECT username FROM users ORDER BY username')->fetchAll(PDO::FETCH_COLUMN);
$opts = '';
foreach ($names as $n) $opts .= '<option value="' . e($n) . '"' . (($_POST['username'] ?? '') === $n ? ' selected' : '') . '>' . e($n) . '</option>';

auth_page('Log in', '
<h1>Welcome back</h1><p class="sub">Log in to Trusty Ofisina</p>
' . ($error ? '<div class="err" role="alert">' . e($error) . '</div>' : '') . '
<form method="post" autocomplete="on">
  <input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">
  <label for="u">Who are you?</label>
  <select id="u" name="username" autocomplete="username">' . $opts . '</select>
  <label for="p">Password</label>
  <input id="p" name="password" type="password" autocomplete="current-password" required autofocus>
  <button type="submit">Log in</button>
</form>');

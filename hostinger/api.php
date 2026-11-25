<?php
// JSON API used by the app (through shim.js): sync records, save, delete, and handle photo uploads.
declare(strict_types=1);
require __DIR__ . '/lib.php';

start_session();
$user = current_user();
if (!$user) fail('unauthenticated', 'Please log in again.', 401);

$action = (string)($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) fail('invalid_argument', 'Your session expired. Reload the page.', 403);
}
session_write_close(); // let parallel requests run

function body_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) fail('invalid_argument', 'Invalid request.');
    return $j;
}

function check_path(array $b): array {
    $col = (string)($b['col'] ?? '');
    $id = (string)($b['id'] ?? '');
    if (!in_array($col, COLLECTIONS, true)) fail('invalid_argument', 'Unknown collection.');
    if (!preg_match('/^[A-Za-z0-9_\-.~:@+]{1,100}$/', $id)) fail('invalid_argument', 'Invalid record id.');
    return [$col, $id];
}

function encode_doc($data): string {
    if (!is_array($data) || array_is_list($data) && $data !== []) fail('invalid_argument', 'A record must be an object.');
    $json = json_encode($data === [] ? new stdClass() : $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) fail('invalid_argument', 'Record could not be saved.');
    if (strlen($json) > MAX_DOC_BYTES) fail('invalid_argument', 'Record is too large.');
    return $json;
}

function save_doc(string $col, string $id, string $json): void {
    $now = now_ms();
    if (is_sqlite()) {
        $sql = 'INSERT INTO docs (col, id, data, updated_at, deleted) VALUES (?, ?, ?, ?, 0)
                ON CONFLICT(col, id) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at, deleted = 0';
    } else {
        $sql = 'INSERT INTO docs (col, id, data, updated_at, deleted) VALUES (?, ?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at), deleted = 0';
    }
    db()->prepare($sql)->execute([$col, $id, $json, $now]);
}


// ---------- Instagram (Graph API, Instagram account linked to a Facebook page) ----------
function graph_base(): string { global $CONFIG; return rtrim($CONFIG['graph_base'] ?? 'https://graph.facebook.com/v21.0', '/'); }
function ig_file(): string { return data_dir() . '/instagram.json'; }
function ig_settings(): ?array { $f = ig_file(); if (!is_file($f)) return null; $j = json_decode((string)file_get_contents($f), true); return is_array($j) ? $j : null; }
function ig_save(array $d): void { file_put_contents(ig_file(), json_encode($d), LOCK_EX); @chmod(ig_file(), 0600); }

function graph(string $method, string $path, array $params): array {
    $url = graph_base() . '/' . ltrim($path, '/');
    $ch = curl_init();
    if ($method === 'GET') { curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params)); }
    else { curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 10]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($body === false) fail('unavailable', 'Could not reach Instagram: ' . $cerr, 502);
    $j = json_decode((string)$body, true);
    if (!is_array($j)) fail('unavailable', 'Instagram sent an unexpected answer.', 502);
    if (isset($j['error'])) {
        $code = (int)($j['error']['code'] ?? 0);
        $msg = (string)($j['error']['error_user_msg'] ?? $j['error']['message'] ?? 'Unknown error');
        if ($code === 190) fail('ig_token', 'The Instagram token has expired or was revoked. Make a new token and connect again in Settings.', 400);
        fail('ig_error', 'Instagram said: ' . $msg, 400);
    }
    if ($status >= 400) fail('ig_error', 'Instagram refused the request (' . $status . ').', 400);
    return $j;
}

function public_base_url(): string {
    global $CONFIG;
    if (!empty($CONFIG['public_base_url'])) return rtrim($CONFIG['public_base_url'], '/');
    $scheme = is_https() ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/api.php'))), '/');
    return "$scheme://$host$dir";
}

switch ($action) {
    case 'sync': {
        // Everything changed since the given time (ms). since=0 returns all live records.
        $since = max(0, (int)($_GET['since'] ?? 0));
        $now = now_ms();
        if ($since === 0) {
            $st = db()->prepare('SELECT col, id, data, updated_at, deleted FROM docs WHERE deleted = 0');
            $st->execute();
        } else {
            $st = db()->prepare('SELECT col, id, data, updated_at, deleted FROM docs WHERE updated_at >= ?');
            $st->execute([$since - 2000]); // small overlap so no write is missed; the app ignores repeats
        }
        $docs = [];
        foreach ($st as $r) {
            $docs[] = ['col' => $r['col'], 'id' => $r['id'], 'deleted' => (int)$r['deleted'] === 1,
                       'data' => (int)$r['deleted'] === 1 ? null : json_decode($r['data'], true)];
        }
        json_out(['now' => $now, 'docs' => $docs]);
    }
    case 'set': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        $b = body_json(); [$col, $id] = check_path($b);
        save_doc($col, $id, encode_doc($b['data'] ?? null));
        json_out(['ok' => true]);
    }
    case 'update': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        $b = body_json(); [$col, $id] = check_path($b);
        $patch = $b['data'] ?? null;
        if (!is_array($patch)) fail('invalid_argument', 'Invalid update.');
        $st = db()->prepare('SELECT data FROM docs WHERE col = ? AND id = ? AND deleted = 0');
        $st->execute([$col, $id]);
        $row = $st->fetchColumn();
        if ($row === false) fail('invalid_argument', 'That record no longer exists.', 404);
        $doc = json_decode((string)$row, true) ?: [];
        foreach ($patch as $k => $v) $doc[$k] = $v;
        save_doc($col, $id, encode_doc($doc));
        json_out(['ok' => true]);
    }
    case 'delete': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        $b = body_json(); [$col, $id] = check_path($b);
        db()->prepare("UPDATE docs SET data = '{}', deleted = 1, updated_at = ? WHERE col = ? AND id = ?")->execute([now_ms(), $col, $id]);
        json_out(['ok' => true]);
    }
    case 'upload': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        global $CONFIG;
        $max = (int)($CONFIG['upload_max_mb'] ?? 20) * 1024 * 1024;
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) fail('too_large', 'That file is too large.');
            fail('invalid_request', 'No file received.');
        }
        if ($f['size'] > $max) fail('too_large', 'That file is too large.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
        if (!isset($allowed[$mime])) fail('unsupported_type', 'Use a photo (JPG, PNG, WebP) or a PDF.');
        $id = bin2hex(random_bytes(16));
        $ext = $allowed[$mime];
        $dest = data_dir() . "/uploads/$id.$ext";
        if (!move_uploaded_file($f['tmp_name'], $dest)) fail('store_unavailable', 'Could not save the file. Check folder permissions.', 500);
        @chmod($dest, 0640);
        $created = is_sqlite() ? gmdate('c') : gmdate('Y-m-d H:i:s');
        db()->prepare('INSERT INTO files (id, ext, mime, size, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $ext, $mime, (int)$f['size'], $created, $user]);
        json_out(['id' => $id, 'url' => 'api.php?action=file&id=' . $id, 'sizeBytes' => (int)$f['size'], 'contentType' => $mime]);
    }
    case 'file': {
        $id = (string)($_GET['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) { http_response_code(404); exit; }
        $st = db()->prepare('SELECT ext, mime, size FROM files WHERE id = ?');
        $st->execute([$id]);
        $f = $st->fetch();
        $path = $f ? data_dir() . "/uploads/$id.{$f['ext']}" : '';
        if (!$f || !is_file($path)) { http_response_code(404); exit; }
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=31536000, immutable');
        header('Content-Disposition: inline; filename="' . $id . '.' . $f['ext'] . '"');
        readfile($path);
        exit;
    }
    case 'file_delete': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        $b = body_json();
        $id = (string)($b['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) fail('invalid_argument', 'Invalid file id.');
        $st = db()->prepare('SELECT ext FROM files WHERE id = ?');
        $st->execute([$id]);
        $ext = $st->fetchColumn();
        if ($ext !== false) {
            @unlink(data_dir() . "/uploads/$id.$ext");
            db()->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
        }
        json_out(['deleted' => $ext !== false]);
    }
    case 'ig_status': {
        $ig = ig_settings();
        json_out($ig && !empty($ig['ig_user_id']) ? ['connected' => true, 'username' => $ig['username'] ?? ''] : ['connected' => false]);
    }
    case 'ig_connect': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        $b = body_json();
        if (!empty($b['choose'])) {
            $pending = ig_settings();
            $pick = (string)($b['ig_user_id'] ?? '');
            foreach (($pending['choices'] ?? []) as $c) {
                if ($c['id'] === $pick) { ig_save(['token' => $pending['token'], 'ig_user_id' => $c['id'], 'username' => $c['username'], 'page' => $c['page']]); json_out(['connected' => true, 'username' => $c['username']]); }
            }
            fail('invalid_argument', 'Pick one of the accounts in the list.');
        }
        $token = trim((string)($b['token'] ?? ''));
        if (strlen($token) < 20) fail('invalid_argument', 'That doesn\'t look like an access token. Copy the whole token.');
        $res = graph('GET', 'me/accounts', ['fields' => 'name,instagram_business_account{id,username}', 'limit' => 50, 'access_token' => $token]);
        $choices = [];
        foreach (($res['data'] ?? []) as $pg) {
            $iga = $pg['instagram_business_account'] ?? null;
            if ($iga && !empty($iga['id'])) $choices[] = ['id' => (string)$iga['id'], 'username' => (string)($iga['username'] ?? ''), 'page' => (string)($pg['name'] ?? '')];
        }
        if (!$choices) fail('ig_error', 'No Instagram professional account was found on the Facebook pages this token can see. Check that the token has the permissions listed in INSTAGRAM.md and that @trusty_autorijschool is linked to your Facebook page.');
        if (count($choices) === 1) { $c = $choices[0]; ig_save(['token' => $token, 'ig_user_id' => $c['id'], 'username' => $c['username'], 'page' => $c['page']]); json_out(['connected' => true, 'username' => $c['username']]); }
        ig_save(['token' => $token, 'choices' => $choices]);
        json_out(['connected' => false, 'choices' => $choices]);
    }
    case 'ig_disconnect': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        @unlink(ig_file());
        json_out(['connected' => false]);
    }
    case 'ig_post': {
        if ($method !== 'POST') fail('invalid_argument', 'Use POST.', 405);
        $ig = ig_settings();
        if (!$ig || empty($ig['ig_user_id'])) fail('ig_error', 'Instagram isn\'t connected yet. Connect it in Settings.');
        $f = $_FILES['image'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) fail('invalid_request', 'No image received.');
        if ($f['size'] > 8 * 1024 * 1024) fail('too_large', 'The image is too large for Instagram (max 8 MB).');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if ($mime !== 'image/jpeg') fail('unsupported_type', 'Instagram needs a JPEG image.');
        $caption = mb_substr((string)($_POST['caption'] ?? ''), 0, 2200);
        @set_time_limit(120);
        // Instagram downloads the image from a public address, so it is placed in ig/ for the moment of posting only.
        $dir = __DIR__ . '/ig';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        $path = "$dir/$name";
        if (!move_uploaded_file($f['tmp_name'], $path)) fail('store_unavailable', 'Could not prepare the image. Check folder permissions.', 500);
        @chmod($path, 0644);
        register_shutdown_function(static function () use ($path) { @unlink($path); }); // runs even when fail() stops the request
        {
            $created = graph('POST', $ig['ig_user_id'] . '/media', ['image_url' => public_base_url() . '/ig/' . $name, 'caption' => $caption, 'access_token' => $ig['token']]);
            $cid = (string)($created['id'] ?? '');
            if ($cid === '') fail('ig_error', 'Instagram didn\'t accept the image.');
            $status = 'IN_PROGRESS';
            for ($i = 0; $i < 30 && $status !== 'FINISHED'; $i++) {
                $st = graph('GET', $cid, ['fields' => 'status_code', 'access_token' => $ig['token']]);
                $status = (string)($st['status_code'] ?? 'FINISHED');
                if ($status === 'ERROR' || $status === 'EXPIRED') fail('ig_error', 'Instagram couldn\'t process the image. Try again.');
                if ($status !== 'FINISHED') sleep(2);
            }
            if ($status !== 'FINISHED') fail('ig_error', 'Instagram is still processing the image. Wait a minute, then check Instagram before trying again.');
            $pub = graph('POST', $ig['ig_user_id'] . '/media_publish', ['creation_id' => $cid, 'access_token' => $ig['token']]);
            $mid = (string)($pub['id'] ?? '');
            $permalink = '';
            if ($mid !== '') { try { $pl = graph('GET', $mid, ['fields' => 'permalink', 'access_token' => $ig['token']]); $permalink = (string)($pl['permalink'] ?? ''); } catch (Throwable $e) {} }
            json_out(['id' => $mid, 'permalink' => $permalink]);
        }
    }
    default:
        fail('invalid_argument', 'Unknown action.', 404);
}

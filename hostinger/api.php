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
    default:
        fail('invalid_argument', 'Unknown action.', 404);
}

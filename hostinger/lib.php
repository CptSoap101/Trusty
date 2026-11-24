<?php
// Shared helpers for Trusty Ofisina on Hostinger: config, database, sessions, security.
declare(strict_types=1);

$CONFIG = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : null;
if ($CONFIG === null && basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'setup.php') {
    header('Location: setup.php');
    exit;
}

const COLLECTIONS = ['clients', 'payments', 'lessons', 'expenses', 'invoices', 'settings'];
const MAX_DOC_BYTES = 262144; // 256 KB per record

// Security headers on every response.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('trusty_sid');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 14, // stay signed in for 14 days
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 14));
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function db(): PDO {
    static $pdo = null;
    global $CONFIG;
    if ($pdo) return $pdo;
    if (!$CONFIG) throw new PDOException('No database configured yet.');
    $dsn = $CONFIG['dsn'] ?? sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $CONFIG['db_host'], $CONFIG['db_name']);
    $pdo = new PDO($dsn, $CONFIG['db_user'] ?? null, $CONFIG['db_pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function is_sqlite(): bool { return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'; }

function create_tables(): void {
    $pdo = db();
    if (is_sqlite()) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, pass_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS docs (col TEXT NOT NULL, id TEXT NOT NULL, data TEXT NOT NULL, updated_at INTEGER NOT NULL, deleted INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (col, id))');
        $pdo->exec('CREATE INDEX IF NOT EXISTS docs_updated ON docs (updated_at)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS files (id TEXT PRIMARY KEY, ext TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL, created_at TEXT NOT NULL, created_by TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, at INTEGER NOT NULL)');
        return;
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL UNIQUE, pass_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE IF NOT EXISTS docs (col VARCHAR(40) NOT NULL, id VARCHAR(100) NOT NULL, data LONGTEXT NOT NULL, updated_at BIGINT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0, PRIMARY KEY (col, id), KEY docs_updated (updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE IF NOT EXISTS files (id CHAR(32) PRIMARY KEY, ext VARCHAR(8) NOT NULL, mime VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL, created_by VARCHAR(64) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (ip VARCHAR(45) NOT NULL, at INT NOT NULL, KEY la_ip (ip, at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function users_exist(): bool {
    try { return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; }
    catch (PDOException $e) { return false; }
}

function current_user(): ?string {
    start_session();
    return $_SESSION['user'] ?? null;
}

function json_out($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $code, string $message, int $status = 400): void {
    json_out(['code' => $code, 'error' => $message], $status);
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45); }

function too_many_attempts(): bool {
    $since = time() - 15 * 60;
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([$since]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at >= ?');
    $st->execute([client_ip(), $since]);
    return (int)$st->fetchColumn() >= 10;
}

function record_failed_attempt(): void {
    db()->prepare('INSERT INTO login_attempts (ip, at) VALUES (?, ?)')->execute([client_ip(), time()]);
}

function data_dir(): string {
    global $CONFIG;
    $dir = $CONFIG['data_dir'] ?? (__DIR__ . '/data');
    if (!is_dir($dir . '/uploads')) @mkdir($dir . '/uploads', 0750, true);
    return $dir;
}

function now_ms(): int { return (int)floor(microtime(true) * 1000); }

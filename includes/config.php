<?php
define('SMILE_VERSION', '1.0');

define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . '/storage/database.sqlite');
define('PHOTO_DIR', APP_ROOT . '/storage/photos');
define('BACKUP_DIR', APP_ROOT . '/storage/backups');
define('LOG_DIR', APP_ROOT . '/logs');
define('LOG_FILE', LOG_DIR . '/app.log');

error_reporting(E_ALL);
ini_set('log_errors', 'On');
ini_set('error_log', LOG_FILE);

date_default_timezone_set('Asia/Kolkata');

function is_installed(): bool {
    return file_exists(DB_PATH) && file_exists(APP_ROOT . '/config.local.php');
}

function load_settings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    if (!is_installed()) return $cache;
    try {
        $db = db();
        $rows = $db->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = $rows ?: [];
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

function setting(string $key, $default = null) {
    $s = load_settings();
    return $s[$key] ?? $default;
}

function is_debug(): bool {
    return setting('debug_mode', '0') === '1';
}

function currency_symbol(): string {
    return setting('currency', '₹');
}

function clinic_name(): string {
    return setting('clinic_name', 'SMILE');
}

function format_money($amount): string {
    return currency_symbol() . number_format((float)$amount, 0, '.', ',');
}

function format_date(?string $date): string {
    if (!$date) return '';
    $ts = strtotime($date);
    if (!$ts) return $date;
    return date('j M Y', $ts);
}

function format_date_long(?string $date): string {
    if (!$date) return '';
    $ts = strtotime($date);
    if (!$ts) return $date;
    return date('j F Y', $ts);
}

function today_date(): string {
    return date('Y-m-d');
}

function today_date_long(): string {
    return date('j F Y');
}

function redirect(string $path): void {
    header("Location: $path");
    exit;
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function set_flash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf'] ?? '';
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function log_error(string $level, string $message, string $file = '', int $line = 0): void {
    $ts = date('Y-m-d H:i:s');
    $entry = "[$ts] [$level] $message";
    if ($file) $entry .= " in $file";
    if ($line) $entry .= " on line $line";
    $entry .= "\n";
    @file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

function smile_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool {
    $levels = [E_ERROR=>'Error', E_WARNING=>'Warning', E_NOTICE=>'Notice', E_USER_ERROR=>'User Error', E_USER_WARNING=>'User Warning', E_USER_NOTICE=>'User Notice'];
    $level = $levels[$errno] ?? 'Unknown';
    log_error($level, $errstr, $errfile, $errline);
    if (is_debug()) {
        // display in dev mode - will be caught by shutdown
    }
    return true;
}

function smile_shutdown_handler(): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        log_error('Fatal', $e['message'], $e['file'], $e['line']);
        if (!headers_sent()) {
            http_response_code(500);
            if (is_debug()) {
                echo "Fatal error: " . e($e['message']) . " in {$e['file']} on line {$e['line']}";
            } else {
                echo "Something went wrong. Please try again.";
            }
        }
    }
}

set_error_handler('smile_error_handler');
register_shutdown_function('smile_shutdown_handler');

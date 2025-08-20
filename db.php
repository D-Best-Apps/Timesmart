<?php
// db.php
declare(strict_types=1);

/**
 * Lightweight .env loader (no Composer dependency).
 * Loads KEY=VALUE pairs into $_ENV and getenv() if a .env file exists.
 */
(function (string $path) {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || trim($line) === '') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v);
        // Strip optional surrounding quotes
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
        putenv("$k=$v");
    }
})(__DIR__ . '/.env');

/** Helper to read env with default */
function env(string $key, ?string $default = null): ?string {
    $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($val === false || $val === null || $val === '') ? $default : $val;
}

// ---- Configuration from env/.env (with sensible defaults) ----
$host = env('DB_HOST', 'localhost');
$user = env('DB_USER', 'timeclock');
$pass = env('DB_PASS', '');
$db   = env('DB_NAME', 'timeclock');
$port = (int) (env('DB_PORT', '3306') ?? 3306);
$tz   = env('APP_TZ', 'America/Chicago'); // optional app DB session TZ

// ---- Connect (throw on errors) ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset('utf8mb4');

    // Try to set named time zone; if the server lacks tz tables, fall back to a fixed offset.
    try {
        $tzEsc = $conn->real_escape_string($tz);
        $conn->query("SET time_zone = '{$tzEsc}'");
    } catch (mysqli_sql_exception $e) {
        // Fallback: use current Central offset; adjust if needed (-05:00 during DST, -06:00 standard)
        $conn->query("SET time_zone = '-05:00'");
    }
} catch (mysqli_sql_exception $e) {
    // In production you’d log this instead of echoing
    http_response_code(500);
    die('Database Connection Failed: ' . $e->getMessage());
}

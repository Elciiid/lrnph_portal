<?php
date_default_timezone_set('Asia/Manila');

// ─── .env Loader (for local development) ────────────────────────────────────
// Reads a .env file next to this project's root when DATABASE_URL isn't already
// set as a real system/server environment variable (e.g. on Vercel it is set).
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\"'");
            // Only set if not already defined by the real environment
            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
    }
}

// ─── Helper: detect if caller expects JSON ───────────────────────────────────
function _db_is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    return $xhr === 'xmlhttprequest'
        || strpos($uri, '/actions/') !== false
        || strpos($uri, '_api.php') !== false
        || strpos($accept, 'application/json') !== false;
}

function _db_fatal(string $title, string $msg, int $code = 500): never {
    if (_db_is_api_request()) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['error' => $title, 'details' => $msg]);
    } else {
        http_response_code($code);
        echo "<div style=\"font-family:sans-serif;padding:50px;text-align:center;\">
                <h2>$title</h2><p>" . htmlspecialchars($msg) . "</p>
              </div>";
    }
    exit;
}

// ─── Validate DATABASE_URL ───────────────────────────────────────────────────
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    _db_fatal(
        'Configuration Error',
        'DATABASE_URL is not set. Add it to your .env file (local) or Vercel environment variables.'
    );
}

$databaseUrl = trim($databaseUrl);
if (strpos($databaseUrl, 'DATABASE_URL=') === 0) {
    $databaseUrl = substr($databaseUrl, 13);
}

$user = $pass = $host = $dbname = null;
$port = 6543; // Supabase Transaction Pooler port

// Regex parser — handles special chars like [], @, : in passwords
if (preg_match('/^postgres(?:ql)?:\/\/([^:]+):(.*)@([^:\/]+)(?::(\d+))?\/(.+)$/', $databaseUrl, $m)) {
    $user   = $m[1];
    $pass   = $m[2];
    $host   = $m[3];
    $port   = $m[4] ?: 6543;
    $dbname = explode('?', $m[5])[0];
} else {
    $parsed = parse_url($databaseUrl);
    $user   = $parsed['user'] ?? null;
    $pass   = $parsed['pass'] ?? null;
    $host   = $parsed['host'] ?? null;
    $port   = $parsed['port'] ?? 6543;
    $dbname = explode('?', ltrim($parsed['path'] ?? '', '/'))[0];
}

if (!$host || !$user || !$dbname) {
    _db_fatal('Configuration Error', 'DATABASE_URL is malformed. Check the format in your .env file.');
}

// ─── Connect ─────────────────────────────────────────────────────────────────
try {
    $dsn  = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // CRITICAL: Register DB-backed session handler BEFORE session_start()
    require_once __DIR__ . '/../utils/session_handler.php';
    $handler = new PdoSessionHandler($conn);
    session_set_save_handler($handler, true);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    _db_fatal(
        'System Unavailable',
        'Database connection failed. ' . $e->getMessage()
    );
}

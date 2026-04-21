<?php
// =============================================
// إعداد قاعدة البيانات والثوابت العامة
// =============================================

// تحميل ملف .env إذا كان موجوداً
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // إزالة علامات الاقتباس إذا وُجدت
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

// ========== ثوابت التطبيق ==========
define('APP_URL',          rtrim($_ENV['APP_URL'] ?? '', '/'));
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 7200));

// مسارات رفع الملفات
define('UPLOAD_DIR', $_ENV['UPLOAD_DIR'] ?? __DIR__ . '/../uploads/');
define('UPLOAD_URL', $_ENV['UPLOAD_URL'] ?? '/uploads');

// ========== مفاتيح الذكاء الاصطناعي ==========
define('GEMINI_API_KEY',    $_ENV['GEMINI_API_KEY']    ?? '');
define('OPENAI_API_KEY',    $_ENV['OPENAI_API_KEY']    ?? '');
define('ANTHROPIC_API_KEY', $_ENV['ANTHROPIC_API_KEY'] ?? '');
define('GAMMA_API_KEY',     $_ENV['GAMMA_API_KEY']     ?? '');
define('ELEVENLABS_API_KEY',$_ENV['ELEVENLABS_API_KEY'] ?? '');
define('HEYGEN_API_KEY',    $_ENV['HEYGEN_API_KEY']    ?? '');

// ========== إعدادات قاعدة البيانات ==========
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'dhakali_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// ========== اتصال قاعدة البيانات ==========
$_pdo = null;

function getDB(): PDO {
    global $_pdo;
    if ($_pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $_pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $_pdo;
}

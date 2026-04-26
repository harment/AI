<?php
// =============================================
// إعدادات قاعدة البيانات والبيئة
// =============================================

if (!function_exists('dhakali_env')) {
    function dhakali_env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string)$value;
    }
}

if (!function_exists('dhakali_load_env')) {
    function dhakali_load_env(string $envPath): void
    {
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if ($name === '') {
                continue;
            }

            $len = strlen($value);
            if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}

dhakali_load_env(__DIR__ . '/../.env');

if (!defined('DB_HOST')) define('DB_HOST', dhakali_env('DB_HOST', 'localhost'));
if (!defined('DB_PORT')) define('DB_PORT', (int)dhakali_env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', dhakali_env('DB_NAME', 'dhakali_db'));
if (!defined('DB_USER')) define('DB_USER', dhakali_env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', dhakali_env('DB_PASS', ''));

if (!defined('APP_URL')) define('APP_URL', rtrim((string)dhakali_env('APP_URL', ''), '/'));
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', (int)dhakali_env('SESSION_LIFETIME', '2592000'));

$projectRoot = dirname(__DIR__);
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', $projectRoot . '/uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', '/uploads/');

if (!defined('GEMINI_API_KEY'))     define('GEMINI_API_KEY',     (string)dhakali_env('GEMINI_API_KEY', ''));
if (!defined('OPENAI_API_KEY'))     define('OPENAI_API_KEY',     (string)dhakali_env('OPENAI_API_KEY', ''));
if (!defined('ANTHROPIC_API_KEY'))  define('ANTHROPIC_API_KEY',  (string)dhakali_env('ANTHROPIC_API_KEY', ''));
if (!defined('GAMMA_API_KEY'))      define('GAMMA_API_KEY',      (string)dhakali_env('GAMMA_API_KEY', ''));
if (!defined('ELEVENLABS_API_KEY')) define('ELEVENLABS_API_KEY', (string)dhakali_env('ELEVENLABS_API_KEY', ''));
if (!defined('HEYGEN_API_KEY'))     define('HEYGEN_API_KEY',     (string)dhakali_env('HEYGEN_API_KEY', ''));

if (!function_exists('getDB')) {
    function getDB(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage(), 0, $e);
        }
    }
}

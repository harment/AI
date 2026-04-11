<?php
// =============================================
// تحميل ملف .env إن وُجد
// =============================================
(function () {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // إزالة علامات الاقتباس الاختيارية
        if (strlen($value) >= 2 && (
            ($value[0] === '"'  && substr($value, -1) === '"') ||
            ($value[0] === "'"  && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {   // لا تلغي متغيرات النظام
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
})();

// =============================================
// إعدادات قاعدة البيانات
// =============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'dhakali_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات التطبيق
define('APP_NAME', 'المساعد الذّكاليّ');
define('APP_URL',  (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('APP_VERSION', '1.0.0');

// مفاتيح الذكاء الاصطناعي (يُعدَّل حسب البيئة)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');

// إعدادات الجلسة
define('SESSION_LIFETIME', 7200); // ساعتان

// مسار رفع الملفات
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

// إنشاء اتصال PDO
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('خطأ في الاتصال بقاعدة البيانات', 500, $e);
        }
    }
    return $pdo;
}

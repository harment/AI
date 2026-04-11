<?php
// =============================================
// وظائف مشتركة
// =============================================
require_once __DIR__ . '/../config/db.php';

// معالج عالمي للاستثناءات غير الملتقطة
set_exception_handler(function (Throwable $e): void {
    http_response_code(500);
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_starts_with($uri, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
            . '<title>خطأ – المساعد الذّكاليّ</title>'
            . '<style>body{font-family:sans-serif;text-align:center;padding:3rem;background:#f9fafb;}'
            . 'h2{color:#c62828;}a{color:#1a237e;}</style></head><body>'
            . '<h2>⚠️ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>يرجى التحقق من إعدادات قاعدة البيانات.</p>'
            . '<a href="/setup.php">🔧 فحص الإعداد</a></body></html>';
    }
    exit;
});

// بدء الجلسة
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// تسجيل دخول الطالب
function loginStudent(string $email, string $password): array|false {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $student = $stmt->fetch();
    if ($student && password_verify($password, $student['password_hash'])) {
        return $student;
    }
    return false;
}

// تسجيل دخول الأستاذ
function loginTeacher(string $email, string $password): array|false {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM teachers WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $teacher = $stmt->fetch();
    if ($teacher && password_verify($password, $teacher['password_hash'])) {
        return $teacher;
    }
    return false;
}

// التحقق من جلسة الطالب
function requireStudent(): array {
    startSession();
    if (empty($_SESSION['student_id'])) {
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch();
    if (!$student) {
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?page=login');
        exit;
    }
    return $student;
}

// التحقق من جلسة الأستاذ
function requireTeacher(): array {
    startSession();
    if (empty($_SESSION['teacher_id'])) {
        header('Location: ' . APP_URL . '/teacher/login.php');
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['teacher_id']]);
    $teacher = $stmt->fetch();
    if (!$teacher) {
        session_destroy();
        header('Location: ' . APP_URL . '/teacher/login.php');
        exit;
    }
    return $teacher;
}

// تنظيف المدخلات
function clean(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// استجابة JSON
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// رفع ملف
function uploadFile(array $file, string $subDir, array $allowedTypes): string|false {
    $uploadPath = UPLOAD_DIR . $subDir . '/';
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0775, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file['type'], $allowedTypes) && !in_array($ext, $allowedTypes)) {
        return false;
    }
    $filename = uniqid('', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadPath . $filename)) {
        return UPLOAD_URL . $subDir . '/' . $filename;
    }
    return false;
}

// تسجيل النشاط
function logActivity(int $studentId, ?int $lessonId, string $action, string $details = '', int $duration = 0): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_log (student_id, lesson_id, action, details, duration_seconds) VALUES (?,?,?,?,?)");
    $stmt->execute([$studentId, $lessonId, $action, $details, $duration]);
}

// الحصول على ترتيب المتصدرين
function getLeaderboard(int $limit = 10): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT s.id, s.name, s.points, COUNT(DISTINCT ss.scholar_id) AS scholars_count
        FROM students s
        LEFT JOIN student_scholars ss ON ss.student_id = s.id
        GROUP BY s.id ORDER BY s.points DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

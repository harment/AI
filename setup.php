<?php
/**
 * ملف التحقق من إعداد التطبيق
 * يمكن حذفه بعد التثبيت الناجح
 */

// إعادة ضبط كلمة مرور الأستاذ الافتراضي
$resetMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_teacher_password'])) {
    try {
        require_once __DIR__ . '/config/db.php';
        $newPass = password_hash('password', PASSWORD_BCRYPT);
        $db = getDB();
        $stmt = $db->prepare("UPDATE teachers SET password_hash = ? WHERE email = 'admin@dhakali.edu'");
        $stmt->execute([$newPass]);
        if ($stmt->rowCount() > 0) {
            $resetMsg = ['type' => 'success', 'text' => '✅ تم إعادة ضبط كلمة مرور الأستاذ الافتراضي إلى: password'];
        } else {
            // إنشاء الحساب إن لم يكن موجوداً
            $stmt = $db->prepare("INSERT INTO teachers (name, email, password_hash) VALUES ('الأستاذ الإداري', 'admin@dhakali.edu', ?)");
            $stmt->execute([$newPass]);
            $resetMsg = ['type' => 'success', 'text' => '✅ تم إنشاء حساب الأستاذ الافتراضي بكلمة مرور: password'];
        }
    } catch (Exception $e) {
        $resetMsg = ['type' => 'danger', 'text' => '❌ خطأ: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')];
    }
}

$checks = [];

// PHP Version
$checks['PHP 8.0+'] = version_compare(PHP_VERSION, '8.0.0', '>=');

// Extensions
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo', 'curl'] as $ext) {
    $checks["PHP ext: $ext"] = extension_loaded($ext);
}

// Directories
$dirs = ['uploads/pdfs', 'uploads/podcasts', 'uploads/videos', 'uploads/images'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) mkdir($path, 0775, true);
    $checks["Dir: $dir"] = is_writable($path);
}

// .env file
$checks['.env file'] = file_exists(__DIR__ . '/.env');

// Gemini API Key configured
$geminiKey = '';
try {
    require_once __DIR__ . '/config/db.php';
    $geminiKey = GEMINI_API_KEY;
} catch (Exception $e) {}
$checks['Gemini API Key'] = !empty($geminiKey);

// DB Connection
try {
    if (!isset($db)) { require_once __DIR__ . '/config/db.php'; }
    $db = getDB();
    $db->query("SELECT 1");
    $checks['Database Connection'] = true;
    
    // Check tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['students', 'teachers', 'courses', 'lessons', 'questions', 'scholars', 'student_games', 'student_scholars', 'activity_log'];
    foreach ($required as $t) {
        $checks["Table: $t"] = in_array($t, $tables);
    }
} catch (Exception $e) {
    $checks['Database Connection'] = false;
}

$allGood = !in_array(false, $checks, true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>فحص الإعداد – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="max-width:700px;margin:2rem auto;padding:1rem;">
  <h1 style="color:var(--primary);">🌿 فحص إعداد التطبيق</h1>
  <div class="card">
    <?php foreach ($checks as $label => $passed): ?>
    <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border);">
      <span style="font-size:1.2rem;"><?= $passed ? '✅' : '❌' ?></span>
      <span style="flex:1;"><?= htmlspecialchars($label) ?></span>
      <span class="badge <?= $passed ? 'badge-primary' : 'badge-danger' ?>"><?= $passed ? 'ناجح' : 'فشل' ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($allGood): ?>
  <div class="alert alert-success" style="margin-top:1rem;">✅ جميع الفحوصات ناجحة! التطبيق جاهز للاستخدام.</div>
  <a href="/" class="btn btn-primary">الانتقال للتطبيق</a>
  <?php else: ?>
  <div class="alert alert-danger" style="margin-top:1rem;">❌ بعض الفحوصات فشلت. يرجى مراجعة الإعدادات.</div>
  <div class="alert alert-info">
    <strong>لإعداد قاعدة البيانات:</strong><br>
    <code>mysql -u root -p &lt; db/schema.sql</code><br><br>
    أو استورد ملف <strong>db/schema.sql</strong> عبر phpMyAdmin.
  </div>
  <?php endif; ?>

  <?php if (empty($geminiKey)): ?>
  <div class="alert alert-warning" style="margin-top:1rem;">
    ⚠️ <strong>مفتاح Gemini API غير مُعيَّن.</strong> لن تعمل ميزات الذكاء الاصطناعي.<br>
    <strong>الحل:</strong> انسخ <code>.env.example</code> إلى <code>.env</code> وأضف مفتاحك:<br>
    <code style="display:block;margin:.5rem 0;background:#f5f5f5;padding:.4rem .7rem;border-radius:4px;">
      cp .env.example .env<br>
      # ثم عدّل GEMINI_API_KEY= بمفتاحك من:<br>
      # https://aistudio.google.com/app/apikey
    </code>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:1.5rem;padding:1rem;">
    <h3 style="margin-bottom:.75rem;">🔑 إعادة ضبط كلمة مرور الأستاذ</h3>
    <?php if ($resetMsg): ?>
    <div class="alert alert-<?= $resetMsg['type'] ?>"><?= $resetMsg['text'] ?></div>
    <?php endif; ?>
    <p style="font-size:.9rem;color:var(--muted);">
      إذا كانت كلمة مرور الأستاذ لا تعمل، اضغط هنا لإعادة ضبطها إلى: <strong>password</strong>
    </p>
    <form method="POST">
      <button type="submit" name="reset_teacher_password" class="btn btn-primary"
              onclick="return confirm('هل تريد إعادة ضبط كلمة مرور الأستاذ إلى: password ؟')">
        🔄 إعادة ضبط كلمة المرور
      </button>
    </form>
  </div>
</body>
</html>

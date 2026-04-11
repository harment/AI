<?php
/**
 * ملف التحقق من إعداد التطبيق
 * يمكن حذفه بعد التثبيت الناجح
 */
$checks = [];

// PHP Version
$checks['PHP 8.0+'] = version_compare(PHP_VERSION, '8.0.0', '>=');

// Extensions
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'] as $ext) {
    $checks["PHP ext: $ext"] = extension_loaded($ext);
}

// Directories
$dirs = ['uploads/pdfs', 'uploads/podcasts', 'uploads/videos', 'uploads/images'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) mkdir($path, 0775, true);
    $checks["Dir: $dir"] = is_writable($path);
}

// DB Connection
try {
    require_once __DIR__ . '/config/db.php';
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
</body>
</html>

<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher = requireTeacher();
$db      = getDB();
$msg = ''; $msgType = 'success';
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    $era  = trim($_POST['era'] ?? '');
    $bio  = trim($_POST['short_bio'] ?? '');
    $works= trim($_POST['works'] ?? '');
    if ($name && $bio) {
        $db->prepare("INSERT INTO scholars (name,era,short_bio,works) VALUES (?,?,?,?)")->execute([$name,$era,$bio,$works]);
        $msg = 'تمت إضافة العالم.';
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { $db->prepare("DELETE FROM scholars WHERE id=?")->execute([$id]); $msg = 'تم الحذف.'; $msgType = 'danger'; }
}

$scholars = $db->query("SELECT s.*, (SELECT COUNT(*) FROM student_scholars ss WHERE ss.scholar_id=s.id) AS discovered_by FROM scholars s ORDER BY s.id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>قائمة العلماء</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand"><button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button><span>👨‍🏫</span><span>لوحة الأستاذ</span></div>
  <ul class="navbar-nav"><li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li></ul>
</nav>
<aside class="sidebar">
  <a href="/teacher/dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"  class="sidebar-link"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"   class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"   class="sidebar-link"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"  class="sidebar-link active"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php"        class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
  <a href="/teacher/question_analysis.php" class="sidebar-link"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
</aside>
<main class="main-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2><i class="fas fa-scroll"></i> قائمة علماء النحو</h2>
    <div style="display:flex;gap:.75rem;">
      <a href="/teacher/ai_generate.php?lesson_id=1&generate=scholar" class="btn btn-accent btn-sm"><i class="fas fa-robot"></i> إضافة بالذكاء</a>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('addModal').style.display='flex'"><i class="fas fa-plus"></i> إضافة يدوياً</button>
    </div>
  </div>
  <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= clean($msg) ?></div><?php endif; ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;">
    <?php foreach ($scholars as $sc): ?>
    <div class="card">
      <div style="display:flex;align-items:flex-start;gap:1rem;">
        <div style="width:52px;height:52px;background:linear-gradient(135deg,var(--dark),var(--primary));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">📜</div>
        <div style="flex:1;">
          <div style="font-weight:800;font-size:1.05rem;"><?= clean($sc['name']) ?></div>
          <?php if ($sc['era']): ?><div style="font-size:.82rem;color:var(--muted);margin-bottom:.4rem;"><?= clean($sc['era']) ?></div><?php endif; ?>
          <p style="font-size:.88rem;line-height:1.5;color:var(--text);"><?= clean($sc['short_bio']) ?></p>
          <?php if ($sc['works']): ?><div style="font-size:.82rem;color:var(--info);margin-top:.35rem;"><i class="fas fa-book"></i> <?= clean($sc['works']) ?></div><?php endif; ?>
          <div style="margin-top:.5rem;font-size:.8rem;color:var(--muted);">اكتشفه <?= $sc['discovered_by'] ?> طالب</div>
        </div>
        <a href="?action=delete&id=<?= $sc['id'] ?>" class="btn btn-danger btn-sm" data-confirm="حذف هذا العالم؟"><i class="fas fa-trash"></i></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>

<div id="addModal" style="display:none;" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header"><span class="modal-title">إضافة عالم يدوياً</span><button class="modal-close" onclick="document.getElementById('addModal').style.display='none'">✕</button></div>
    <form method="POST"><input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">الاسم *</label><input type="text" name="name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">العصر / القرن</label><input type="text" name="era" class="form-control" placeholder="مثال: القرن الثاني الهجري"></div>
      <div class="form-group"><label class="form-label">السيرة الموجزة *</label><textarea name="short_bio" class="form-control" rows="3" required></textarea></div>
      <div class="form-group"><label class="form-label">أبرز المؤلفات</label><input type="text" name="works" class="form-control"></div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> حفظ</button>
    </form>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>

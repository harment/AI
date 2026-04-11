<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher = requireTeacher();
$db      = getDB();

$msg = ''; $msgType = 'success';
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $icon  = trim($_POST['icon'] ?? 'fa-book');
    $color = trim($_POST['color'] ?? '#4B8B3B');
    if ($name) {
        $db->prepare("INSERT INTO courses (teacher_id, name, description, icon, color) VALUES (?,?,?,?,?)")->execute([$teacher['id'], $name, $desc, $icon, $color]);
        $msg = 'تم إضافة المقرر بنجاح.';
    }
} elseif ($action === 'edit') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $icon  = trim($_POST['icon'] ?? 'fa-book');
    $color = trim($_POST['color'] ?? '#4B8B3B');
    $status= $_POST['status'] ?? 'active';
    if ($id && $name) {
        $db->prepare("UPDATE courses SET name=?,description=?,icon=?,color=?,status=? WHERE id=?")->execute([$name, $desc, $icon, $color, $status, $id]);
        $msg = 'تم تحديث المقرر.';
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $db->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
        $msg = 'تم حذف المقرر.'; $msgType = 'danger';
    }
}

$courses = $db->query("SELECT c.*, COUNT(l.id) AS lessons_count FROM courses c LEFT JOIN lessons l ON l.course_id = c.id GROUP BY c.id ORDER BY c.id")->fetchAll();

$icons = ['fa-book','fa-book-open','fa-scroll','fa-graduation-cap','fa-pen-nib','fa-language','fa-star','fa-mosque','fa-feather-alt'];
$colors = ['#2E7D32','#1565C0','#6A1B9A','#AD1457','#E65100','#37474F','#00695C','#283593'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>إدارة المقررات</title>
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
  <a href="/teacher/courses.php"   class="sidebar-link active"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"   class="sidebar-link"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"  class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
</aside>
<main class="main-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2><i class="fas fa-book"></i> إدارة المقررات</h2>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
      <i class="fas fa-plus"></i> إضافة مقرر
    </button>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= clean($msg) ?></div>
  <?php endif; ?>

  <div class="courses-grid">
    <?php foreach ($courses as $c): ?>
    <div class="course-card">
      <div class="course-banner" style="background:linear-gradient(135deg,<?= clean($c['color']) ?>,<?= clean($c['color']) ?>99);">
        <i class="fas <?= clean($c['icon']) ?>" style="color:#fff;font-size:3rem;"></i>
      </div>
      <div class="course-body">
        <div class="course-title"><?= clean($c['name']) ?></div>
        <div class="course-meta" style="margin-bottom:.75rem;">
          <span><i class="fas fa-layer-group"></i> <?= $c['lessons_count'] ?> درس</span>
          <span class="badge <?= $c['status']==='active' ? 'badge-primary' : 'badge-danger' ?>"><?= $c['status']==='active' ? 'نشط' : 'أرشيف' ?></span>
        </div>
        <?php if ($c['description']): ?>
        <p style="font-size:.85rem;color:var(--muted);margin-bottom:.75rem;"><?= clean($c['description']) ?></p>
        <?php endif; ?>
        <div style="display:flex;gap:.5rem;">
          <button class="btn btn-info btn-sm" onclick="editCourse(<?= htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>)"><i class="fas fa-edit"></i> تعديل</button>
          <a href="/teacher/lessons.php?course_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-layer-group"></i> الدروس</a>
          <a href="?action=delete&id=<?= $c['id'] ?>" class="btn btn-danger btn-sm" data-confirm="هل تريد حذف هذا المقرر وجميع دروسه؟"><i class="fas fa-trash"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($courses)): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد مقررات بعد.</div>
    <?php endif; ?>
  </div>
</main>

<!-- Add Modal -->
<div id="addModal" style="display:none;" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">إضافة مقرر جديد</span>
      <button class="modal-close" onclick="document.getElementById('addModal').style.display='none'">✕</button>
    </div>
    <form method="POST"><input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">اسم المقرر *</label><input type="text" name="name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label">الأيقونة</label>
          <select name="icon" class="form-control">
            <?php foreach ($icons as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">اللون</label>
          <select name="color" class="form-control">
            <?php foreach ($colors as $cl): ?><option value="<?= $cl ?>" style="background:<?= $cl ?>;color:#fff;"><?= $cl ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> حفظ</button>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display:none;" class="modal-backdrop">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">تعديل المقرر</span>
      <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'">✕</button>
    </div>
    <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
      <div class="form-group"><label class="form-label">اسم المقرر *</label><input type="text" name="name" id="editName" class="form-control" required></div>
      <div class="form-group"><label class="form-label">الوصف</label><textarea name="description" id="editDesc" class="form-control" rows="2"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
          <label class="form-label">الأيقونة</label>
          <select name="icon" id="editIcon" class="form-control">
            <?php foreach ($icons as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">الحالة</label>
          <select name="status" id="editStatus" class="form-control">
            <option value="active">نشط</option>
            <option value="archived">أرشيف</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> حفظ التعديلات</button>
    </form>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';
function editCourse(c) {
  document.getElementById('editId').value     = c.id;
  document.getElementById('editName').value   = c.name;
  document.getElementById('editDesc').value   = c.description || '';
  document.getElementById('editIcon').value   = c.icon;
  document.getElementById('editStatus').value = c.status;
  document.getElementById('editModal').style.display = 'flex';
}
</script>
</body>
</html>

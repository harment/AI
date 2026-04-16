<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher = requireTeacher();
$db      = getDB();

// Handle actions
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$msg     = '';
$msgType = 'success';

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
    $msg = 'تم حذف الطالب.'; $msgType = 'danger';
}

// Filters
$search = trim($_GET['q'] ?? '');
$level  = $_GET['level'] ?? '';
$where  = 'WHERE 1';
$params = [];
if ($search) { $where .= ' AND (name LIKE ? OR university_id LIKE ? OR email LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
if ($level)  { $where .= ' AND level = ?'; $params[] = $level; }

$students = $db->prepare("SELECT s.*, COUNT(DISTINCT sg.id) AS games_played FROM students s LEFT JOIN student_games sg ON sg.student_id = s.id $where GROUP BY s.id ORDER BY s.points DESC");
$students->execute($params);
$students = $students->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>إدارة الطلاب</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand"><button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button><span>👨‍🏫</span><span>لوحة الأستاذ</span></div>
  <ul class="navbar-nav">
    <li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
  </ul>
</nav>
<aside class="sidebar">
  <a href="/teacher/dashboard.php"  class="sidebar-link"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"   class="sidebar-link active"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"    class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"    class="sidebar-link"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"   class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php"        class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
  <a href="/teacher/question_analysis.php" class="sidebar-link"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
</aside>
<main class="main-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2><i class="fas fa-users"></i> إدارة الطلاب</h2>
    <a href="/teacher/analytics.php?export=excel" class="btn btn-primary btn-sm"><i class="fas fa-file-excel"></i> تصدير Excel</a>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= clean($msg) ?></div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="GET" style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <input type="text" name="q" value="<?= clean($search) ?>" placeholder="بحث بالاسم أو الرقم أو البريد…" class="form-control" style="flex:1;min-width:200px;">
    <select name="level" class="form-control" style="width:auto;">
      <option value="">كل المستويات</option>
      <option value="beginner" <?= $level==='beginner' ? 'selected' : '' ?>>مبتدئ</option>
      <option value="intermediate" <?= $level==='intermediate' ? 'selected' : '' ?>>متوسط</option>
      <option value="advanced" <?= $level==='advanced' ? 'selected' : '' ?>>متقدم</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> بحث</button>
    <a href="/teacher/students.php" class="btn btn-outline btn-sm">إعادة</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>الاسم</th><th>الرقم الجامعي</th><th>المستوى</th><th>السنة</th><th>النقاط</th><th>مغامرات</th><th>الحالة</th><th>إجراءات</th></tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--muted);">لا يوجد طلاب.</td></tr>
        <?php endif; ?>
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><strong><?= clean($s['name']) ?></strong><br><small style="color:var(--muted);"><?= clean($s['email']) ?></small></td>
          <td><?= clean($s['university_id']) ?></td>
          <td><?= ['beginner'=>'مبتدئ','intermediate'=>'متوسط','advanced'=>'متقدم'][$s['level']] ?? $s['level'] ?></td>
          <td><?= clean($s['study_year']) ?></td>
          <td><strong style="color:var(--accent);"><?= number_format($s['points']) ?></strong></td>
          <td><?= $s['games_played'] ?></td>
          <td><?= $s['is_active'] ? '<span class="badge badge-primary">نشط</span>' : '<span class="badge badge-danger">موقوف</span>' ?></td>
          <td>
            <a href="/teacher/analytics.php?student_id=<?= $s['id'] ?>" class="btn btn-info btn-sm" title="تحليل"><i class="fas fa-chart-line"></i></a>
            <a href="?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" data-confirm="هل تريد حذف هذا الطالب؟" title="حذف"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher = requireTeacher();
$db      = getDB();

// Stats
$totalStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalCourses  = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalLessons  = $db->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalGames    = $db->query("SELECT COUNT(*) FROM student_games WHERE completed = 1")->fetchColumn();

// Recent students
$recentStudents = $db->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Top students
$topStudents = $db->query("SELECT s.id, s.name, s.points, COUNT(DISTINCT ss.scholar_id) scholars FROM students s LEFT JOIN student_scholars ss ON ss.student_id = s.id GROUP BY s.id ORDER BY s.points DESC LIMIT 5")->fetchAll();

// Active lessons
$openLessons = $db->query("SELECT l.*, c.name AS course_name FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.is_open = 1 ORDER BY l.id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة الأستاذ – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">
    <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button>
    <span>👨‍🏫</span><span>لوحة الأستاذ</span>
  </div>
  <ul class="navbar-nav">
    <li><span class="nav-link"><i class="fas fa-user"></i> <?= clean($teacher['name']) ?></span></li>
    <li><a href="/api/auth.php?action=logout_teacher" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
  </ul>
</nav>
<aside class="sidebar">
  <div class="sidebar-section">الإدارة</div>
  <a href="/teacher/dashboard.php"  class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> الرئيسية</a>
  <a href="/teacher/students.php"   class="sidebar-link"><i class="fas fa-users"></i> الطلاب</a>
  <a href="/teacher/courses.php"    class="sidebar-link"><i class="fas fa-book"></i> المقررات</a>
  <a href="/teacher/lessons.php"    class="sidebar-link"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"   class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php"        class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
  <a href="/teacher/question_analysis.php" class="sidebar-link"><i class="fas fa-chart-line"></i> تحليل الأسئلة</a>
</aside>
<main class="main-content">
  <h2 style="margin-bottom:1.5rem;">لوحة التحكم الرئيسية</h2>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-users" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-label">طالب مسجل</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-book" style="color:var(--accent);"></i></div>
      <div class="stat-value"><?= $totalCourses ?></div>
      <div class="stat-label">مقرر</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-layer-group" style="color:var(--info);"></i></div>
      <div class="stat-value"><?= $totalLessons ?></div>
      <div class="stat-label">درس</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-gamepad" style="color:#C2185B;"></i></div>
      <div class="stat-value"><?= $totalGames ?></div>
      <div class="stat-label">مغامرة مكتملة 100%</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
    <!-- Top students -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-medal"></i> أفضل الطلاب</div>
        <a href="/teacher/students.php" class="btn btn-outline btn-sm">الكل</a>
      </div>
      <ul class="leaderboard">
        <?php foreach ($topStudents as $i => $s): ?>
        <li>
          <span class="rank-num rank-<?= $i+1 ?>"><?= $i+1 ?></span>
          <span class="lb-name"><?= clean($s['name']) ?></span>
          <span class="lb-pts"><?= number_format($s['points']) ?> نقطة</span>
          <span class="badge badge-primary"><?= $s['scholars'] ?> علماء</span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Open lessons -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-unlock"></i> الدروس المفتوحة</div>
        <a href="/teacher/lessons.php" class="btn btn-outline btn-sm">الكل</a>
      </div>
      <?php if (empty($openLessons)): ?>
      <p style="color:var(--muted);font-size:.9rem;">لا توجد دروس مفتوحة.</p>
      <?php else: ?>
      <?php foreach ($openLessons as $l): ?>
      <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem 0;border-bottom:1px solid var(--border);">
        <span style="font-size:1.2rem;">📗</span>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:.9rem;"><?= clean($l['name']) ?></div>
          <div style="font-size:.8rem;color:var(--muted);"><?= clean($l['course_name']) ?></div>
        </div>
        <a href="/teacher/lessons.php?toggle=<?= $l['id'] ?>&csrf=<?= $_SESSION['teacher_id'] ?>" class="btn btn-danger btn-sm">إغلاق</a>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent students -->
  <div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-user-plus"></i> أحدث الطلاب المسجلين</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الاسم</th><th>الرقم الجامعي</th><th>المستوى</th><th>السنة</th><th>النقاط</th><th>تاريخ التسجيل</th></tr></thead>
        <tbody>
          <?php foreach ($recentStudents as $s): ?>
          <tr>
            <td><?= clean($s['name']) ?></td>
            <td><?= clean($s['university_id']) ?></td>
            <td><?= clean($s['level']) ?></td>
            <td><?= clean($s['study_year']) ?></td>
            <td><strong style="color:var(--accent);"><?= $s['points'] ?></strong></td>
            <td style="font-size:.82rem;"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>

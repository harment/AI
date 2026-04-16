<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher   = requireTeacher();
$db        = getDB();
$studentId = (int)($_GET['student_id'] ?? 0);

// Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_report_' . date('Ymd') . '.csv"');
    header('Pragma: no-cache');
    $bom = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    echo $bom;
    echo "الاسم,الرقم الجامعي,المستوى,السنة,النقاط,عدد العلماء,المغامرات,تاريخ التسجيل\n";
    $rows = $db->query("SELECT s.name, s.university_id, s.level, s.study_year, s.points, COUNT(DISTINCT ss.scholar_id) scholars, COUNT(DISTINCT sg.id) games, s.created_at FROM students s LEFT JOIN student_scholars ss ON ss.student_id=s.id LEFT JOIN student_games sg ON sg.student_id=s.id GROUP BY s.id ORDER BY s.points DESC")->fetchAll();
    foreach ($rows as $r) {
        echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $r)) . "\n";
    }
    exit;
}

// ---- Single student analysis ----
$student = null;
$studentGames = [];
$studentActivity = [];
if ($studentId) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id=?"); $stmt->execute([$studentId]); $student = $stmt->fetch();
    $sgStmt = $db->prepare("SELECT sg.*, l.name AS lname FROM student_games sg LEFT JOIN lessons l ON l.id=sg.lesson_id WHERE sg.student_id=? ORDER BY sg.played_at DESC");
    $sgStmt->execute([$studentId]); $studentGames = $sgStmt->fetchAll();
    $actStmt = $db->prepare("SELECT * FROM activity_log WHERE student_id=? ORDER BY created_at DESC LIMIT 20");
    $actStmt->execute([$studentId]); $studentActivity = $actStmt->fetchAll();
}

// ---- Overall stats ----
$totalPts   = $db->query("SELECT SUM(points) FROM students")->fetchColumn() ?: 0;
$avgPts     = $db->query("SELECT AVG(points) FROM students")->fetchColumn() ?: 0;
$maxPts     = $db->query("SELECT MAX(points) FROM students")->fetchColumn() ?: 0;

// Games per lesson
$lessonStats = $db->query("SELECT l.name, COUNT(*) plays, AVG(sg.points_earned) avg_pts, SUM(sg.completed) wins FROM lessons l LEFT JOIN student_games sg ON sg.lesson_id=l.id GROUP BY l.id ORDER BY plays DESC")->fetchAll();

// Weak lessons: played at least once and win rate < 50%
$weakLessons = array_filter($lessonStats, fn($ls) => $ls['plays'] > 0 && (($ls['wins'] ?? 0) / $ls['plays']) < 0.5);
usort($weakLessons, fn($a, $b) => (($a['wins'] ?? 0) / max($a['plays'], 1)) <=> (($b['wins'] ?? 0) / max($b['plays'], 1)));
$weakLessons = array_slice($weakLessons, 0, 5);

// Time spent
$timeStats = $db->query("SELECT s.name, SUM(al.duration_seconds) total_sec FROM students s LEFT JOIN activity_log al ON al.student_id=s.id GROUP BY s.id ORDER BY total_sec DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>التحليلات</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
  <a href="/teacher/scholars.php"  class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php" class="sidebar-link active"><i class="fas fa-chart-bar"></i> التحليلات</a>
</aside>
<main class="main-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 style="font-size:1.4rem;"><i class="fas fa-chart-bar" style="color:var(--accent);"></i> تحليل التقدم والأداء</h2>
    <a href="?export=excel" class="btn btn-primary btn-sm"><i class="fas fa-file-excel"></i> تصدير Excel</a>
  </div>

  <?php if ($student): ?>
  <!-- ---- Single Student Analysis ---- -->
  <div class="alert alert-info"><i class="fas fa-user"></i> تحليل الطالب: <strong><?= clean($student['name']) ?></strong></div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-star" style="color:var(--accent);"></i></div><div class="stat-value"><?= number_format($student['points']) ?></div><div class="stat-label">نقطة</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-gamepad" style="color:var(--primary);"></i></div><div class="stat-value"><?= count($studentGames) ?></div><div class="stat-label">لعبة</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-check" style="color:var(--info);"></i></div><div class="stat-value"><?= count(array_filter($studentGames, fn($g) => $g['completed'])) ?></div><div class="stat-label">فوز</div></div>
  </div>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title">سجل المغامرات</div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>الدرس</th><th>النقاط</th><th>المحاولات</th><th>النتيجة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($studentGames as $g): ?>
        <tr><td><?= clean($g['lname'] ?? '-') ?></td><td><strong style="color:var(--accent);"><?= $g['points_earned'] ?></strong></td><td><?= $g['attempts'] ?></td>
        <td><?= $g['completed'] ? '<span class="badge badge-primary">✅ فوز</span>' : '<span class="badge badge-danger">❌</span>' ?></td>
        <td style="font-size:.82rem;"><?= date('d/m/Y', strtotime($g['played_at'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <a href="/teacher/analytics.php" class="btn btn-outline"><i class="fas fa-arrow-right"></i> عودة</a>

  <?php else: ?>
  <!-- ---- Overall Analysis ---- -->
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-star" style="color:var(--primary);"></i></div><div class="stat-value"><?= number_format($totalPts) ?></div><div class="stat-label">مجموع النقاط</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-calculator" style="color:var(--accent);"></i></div><div class="stat-value"><?= number_format($avgPts, 1) ?></div><div class="stat-label">متوسط النقاط</div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-trophy" style="color:#C2185B;"></i></div><div class="stat-value"><?= number_format($maxPts) ?></div><div class="stat-label">أعلى نقاط</div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
    <!-- Lesson engagement -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-gamepad"></i> تفاعل الطلاب بالدرس</div></div>
      <canvas id="lessonChart" height="200"></canvas>
    </div>
    <!-- Time on platform -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-clock"></i> وقت التعلم (أعلى 10)</div></div>
      <canvas id="timeChart" height="200"></canvas>
    </div>
  </div>

  <!-- Weak points section -->
  <div class="card" style="margin-bottom:1.5rem;border-right:4px solid var(--accent);">
    <div class="card-header" style="border-bottom-color:var(--accent);">
      <div class="card-title" style="font-size:1rem;color:var(--accent);"><i class="fas fa-exclamation-triangle"></i> نقاط الضعف – دروس تحتاج إلى مراجعة</div>
    </div>
    <?php if (empty($weakLessons)): ?>
    <p style="color:var(--muted);font-size:.9rem;"><i class="fas fa-check-circle" style="color:var(--primary);"></i> لا توجد دروس بنسبة فوز منخفضة حالياً. عمل رائع!</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الدرس</th><th>عدد اللعبات</th><th>نسبة الفوز</th><th>متوسط النقاط</th></tr></thead>
        <tbody>
          <?php foreach ($weakLessons as $ls): ?>
          <?php $rate = $ls['plays'] ? round(($ls['wins'] / $ls['plays']) * 100) : 0; ?>
          <tr>
            <td><strong><?= clean($ls['name']) ?></strong></td>
            <td><?= $ls['plays'] ?></td>
            <td>
              <span style="color:<?= $rate < 25 ? 'var(--danger)' : 'var(--accent)'; ?>;font-weight:700;"><?= $rate ?>%</span>
              <div style="background:#eee;border-radius:4px;height:6px;margin-top:4px;width:100px;">
                <div style="background:<?= $rate < 25 ? 'var(--danger)' : 'var(--accent)'; ?>;width:<?= $rate ?>%;height:100%;border-radius:4px;"></div>
              </div>
            </td>
            <td><?= $ls['plays'] ? number_format($ls['avg_pts'], 1) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Lesson stats table -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-table"></i> إحصائيات الدروس</div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>الدرس</th><th>عدد اللعبات</th><th>متوسط النقاط</th><th>نسبة الفوز</th></tr></thead>
      <tbody>
        <?php foreach ($lessonStats as $ls): ?>
        <tr>
          <td><?= clean($ls['name']) ?></td>
          <td><?= $ls['plays'] ?: 0 ?></td>
          <td><?= $ls['plays'] ? number_format($ls['avg_pts'], 1) : '-' ?></td>
          <td><?= $ls['plays'] ? number_format(($ls['wins'] / $ls['plays']) * 100, 0) . '%' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>
</main>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';
<?php if (!$studentId): ?>
// Chart.js charts
const lessonLabels = <?= json_encode(array_column($lessonStats, 'name'), JSON_UNESCAPED_UNICODE) ?>;
const lessonPlays  = <?= json_encode(array_column($lessonStats, 'plays')) ?>;
const timeLabels   = <?= json_encode(array_column($timeStats, 'name'), JSON_UNESCAPED_UNICODE) ?>;
const timeSecs     = <?= json_encode(array_map(fn($r) => round($r['total_sec'] / 60, 1), $timeStats)) ?>;

new Chart(document.getElementById('lessonChart'), {
  type: 'bar',
  data: { labels: lessonLabels, datasets: [{ label: 'عدد اللعبات', data: lessonPlays, backgroundColor: '#4CAF50' }] },
  options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { family: 'Tajawal' } } } } }
});
new Chart(document.getElementById('timeChart'), {
  type: 'bar',
  data: { labels: timeLabels, datasets: [{ label: 'دقائق', data: timeSecs, backgroundColor: '#1565C0' }] },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { family: 'Tajawal' } } } } }
});
<?php endif; ?>
</script>
</body>
</html>

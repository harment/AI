<?php
require_once __DIR__ . '/../includes/functions.php';
$student = requireStudent();
$db      = getDB();

// Fetch courses
$courses = $db->query("SELECT c.*, COUNT(l.id) AS lessons_count FROM courses c LEFT JOIN lessons l ON l.course_id = c.id WHERE c.status = 'active' GROUP BY c.id ORDER BY c.id")->fetchAll();

// Student scholars count
$scholarsCount = $db->prepare("SELECT COUNT(*) FROM student_scholars WHERE student_id = ?");
$scholarsCount->execute([$student['id']]);
$scholarsCount = $scholarsCount->fetchColumn();

// Student game completions
$gamesPlayed = $db->prepare("SELECT COUNT(*) FROM student_games WHERE student_id = ? AND completed = 1");
$gamesPlayed->execute([$student['id']]);
$gamesPlayed = $gamesPlayed->fetchColumn();

// Student incomplete adventures
$gamesIncomplete = $db->prepare("SELECT COUNT(*) FROM student_games WHERE student_id = ? AND completed = 0");
$gamesIncomplete->execute([$student['id']]);
$gamesIncomplete = $gamesIncomplete->fetchColumn();

// Activity log (last 5)
$activities = $db->prepare("SELECT al.*, l.name AS lesson_name FROM activity_log al LEFT JOIN lessons l ON l.id = al.lesson_id WHERE al.student_id = ? ORDER BY al.created_at DESC LIMIT 5");
$activities->execute([$student['id']]);
$activities = $activities->fetchAll();

$leaderboard = getLeaderboard(5);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة الطالب – المساعد الذّكاليّ</title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" href="/assets/icons/icon-192.png">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<!-- Navbar -->
<nav class="navbar">
  <div class="navbar-brand">
    <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button>
    <span>🌿</span>
    <span>المساعد الذّكاليّ</span>
  </div>
  <ul class="navbar-nav">
    <li><a href="/student/profile.php" class="nav-link"><i class="fas fa-user"></i> <?= clean($student['name']) ?></a></li>
    <li><a href="/api/auth.php?action=logout" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
  </ul>
</nav>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-section">القائمة الرئيسية</div>
  <a href="/student/dashboard.php" class="sidebar-link active"><i class="fas fa-home"></i> الرئيسية</a>
  <a href="/student/courses.php"   class="sidebar-link"><i class="fas fa-book-open"></i> مقرراتي</a>
  <a href="/student/profile.php"   class="sidebar-link"><i class="fas fa-trophy"></i> نقاطي وعلمائي</a>
  <div class="sidebar-section">التعلم</div>
  <a href="#leaderboard" class="sidebar-link"><i class="fas fa-medal"></i> المتصدرون</a>
</aside>

<!-- Main Content -->
<main class="main-content">
  <h2 style="margin-bottom:1.5rem;">أهلاً، <?= clean($student['name']) ?> 👋</h2>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-star" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= number_format($student['points']) ?></div>
      <div class="stat-label">نقطة مكتسبة</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-scroll" style="color:var(--accent);"></i></div>
      <div class="stat-value"><?= $scholarsCount ?></div>
      <div class="stat-label">علماء مُكتشفون</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-gamepad" style="color:var(--info);"></i></div>
      <div class="stat-value"><?= $gamesPlayed ?></div>
      <div class="stat-label">مغامرة مكتملة 100%</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-book" style="color:#C2185B;"></i></div>
      <div class="stat-value"><?= count($courses) ?></div>
      <div class="stat-label">مقررات متاحة</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFEBEE;"><i class="fas fa-times-circle" style="color:var(--danger);"></i></div>
      <div class="stat-value"><?= $gamesIncomplete ?></div>
      <div class="stat-label">مغامرات غير مكتملة 100%</div>
    </div>
  </div>

  <div class="dashboard-panel" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;align-items:start;">
    <!-- Courses -->
    <div>
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
        <h3 class="card-title"><i class="fas fa-book-open"></i> مقرراتي</h3>
        <a href="/student/courses.php" class="btn btn-outline btn-sm">عرض الكل</a>
      </div>
      <?php if (empty($courses)): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد مقررات متاحة حالياً.</div>
      <?php else: ?>
      <div class="courses-grid">
        <?php foreach ($courses as $c): ?>
        <a href="/student/courses.php?id=<?= $c['id'] ?>" style="text-decoration:none;">
          <div class="course-card">
            <div class="course-banner" style="background:linear-gradient(135deg,<?= clean($c['color']) ?>,<?= clean($c['color']) ?>aa);">
              <i class="fas <?= clean($c['icon']) ?>" style="color:#fff;"></i>
            </div>
            <div class="course-body">
              <div class="course-title"><?= clean($c['name']) ?></div>
              <div class="course-meta">
                <span><i class="fas fa-layer-group"></i> <?= $c['lessons_count'] ?> درس</span>
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar widgets -->
    <div>
      <!-- Leaderboard -->
      <div class="card" id="leaderboard" style="margin-bottom:1.5rem;">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-medal"></i> المتصدرون</div>
        </div>
        <ul class="leaderboard">
          <?php foreach ($leaderboard as $i => $lb): ?>
          <li>
            <span class="rank-num rank-<?= $i+1 ?>"><?= $i+1 ?></span>
            <span class="lb-name"><?= clean($lb['name']) ?></span>
            <span class="lb-pts"><?= number_format($lb['points']) ?> نقطة</span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Recent activity -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-history"></i> آخر نشاط</div>
        </div>
        <?php if (empty($activities)): ?>
          <p style="color:var(--muted);font-size:.9rem;">لا يوجد نشاط بعد.</p>
        <?php else: ?>
        <?php foreach ($activities as $act): ?>
        <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border);">
          <div style="width:36px;height:36px;background:var(--bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;">
            <?= $act['action'] === 'login' ? '🔑' : ($act['action'] === 'game_win' ? '🏆' : '📖') ?>
          </div>
          <div>
            <div style="font-size:.88rem;font-weight:600;"><?= clean($act['action']) ?></div>
            <?php if ($act['lesson_name']): ?>
            <div style="font-size:.8rem;color:var(--muted);"><?= clean($act['lesson_name']) ?></div>
            <?php endif; ?>
          </div>
          <div style="margin-right:auto;font-size:.78rem;color:var(--muted);"><?= date('d/m', strtotime($act['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<!-- Chatbot -->
<button class="chatbot-toggle" id="chatbotToggle" title="المساعد الذكي"><i class="fas fa-robot"></i></button>
<div class="chatbot-box" id="chatbotBox">
  <div class="chatbot-header">
    <i class="fas fa-robot"></i>
    <span class="chatbot-title">المساعد الذكي</span>
    <button class="chatbot-close" id="chatbotClose"><i class="fas fa-times"></i></button>
  </div>
  <div class="chatbot-msgs" id="chatbotMsgs"></div>
  <form class="chatbot-input" id="chatbotForm">
    <input type="text" id="chatbotInput" placeholder="اكتب سؤالك هنا…" autocomplete="off">
    <button type="submit"><i class="fas fa-paper-plane"></i></button>
  </form>
</div>

<script src="/assets/js/app.js"></script>
<script>
if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
// Show sidebar toggle on mobile
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';
</script>
</body>
</html>

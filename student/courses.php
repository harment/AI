<?php
require_once __DIR__ . '/../includes/functions.php';
$student = requireStudent();
$db      = getDB();

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($courseId) {
    // Single course – show lessons
    $course = $db->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
    $course->execute([$courseId]);
    $course = $course->fetch();
    if (!$course) { header('Location: /student/courses.php'); exit; }

    $lessons = $db->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order, id");
    $lessons->execute([$courseId]);
    $lessons = $lessons->fetchAll();

    // Student progress per lesson
    $progressMap = [];
    if ($lessons) {
        $ids  = implode(',', array_column($lessons, 'id'));
        $prog = $db->query("SELECT lesson_id, MAX(completed) AS done, SUM(points_earned) AS pts FROM student_games WHERE student_id = {$student['id']} AND lesson_id IN ($ids) GROUP BY lesson_id")->fetchAll();
        foreach ($prog as $p) $progressMap[$p['lesson_id']] = $p;
    }
} else {
    // All courses
    $courses = $db->query("SELECT c.*, COUNT(l.id) AS lessons_count FROM courses c LEFT JOIN lessons l ON l.course_id = c.id WHERE c.status = 'active' GROUP BY c.id ORDER BY c.id")->fetchAll();
}

$pageTitle = $courseId ? clean($course['name']) : 'مقرراتي';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">
    <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button>
    <span>🌿</span><span>المساعد الذّكاليّ</span>
  </div>
  <ul class="navbar-nav">
    <li><a href="/student/dashboard.php" class="nav-link"><i class="fas fa-home"></i> الرئيسية</a></li>
    <li><a href="/api/auth.php?action=logout" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
  </ul>
</nav>
<aside class="sidebar">
  <a href="/student/dashboard.php" class="sidebar-link"><i class="fas fa-home"></i> الرئيسية</a>
  <a href="/student/courses.php"   class="sidebar-link active"><i class="fas fa-book-open"></i> مقرراتي</a>
  <a href="/student/profile.php"   class="sidebar-link"><i class="fas fa-trophy"></i> نقاطي وعلمائي</a>
</aside>
<main class="main-content">
  <!-- Breadcrumb -->
  <div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;font-size:.9rem;color:var(--muted);">
    <a href="/student/courses.php">مقرراتي</a>
    <?php if ($courseId): ?>
    <i class="fas fa-chevron-left" style="font-size:.75rem;"></i>
    <span style="color:var(--text);font-weight:600;"><?= clean($course['name']) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($courseId): ?>
  <!-- ---- Lessons list ---- -->
  <h2 style="margin-bottom:.5rem;"><?= clean($course['name']) ?></h2>
  <?php if ($course['description']): ?>
  <p style="color:var(--muted);margin-bottom:1.5rem;"><?= clean($course['description']) ?></p>
  <?php endif; ?>

  <?php if (empty($lessons)): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد دروس في هذا المقرر بعد.</div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <?php foreach ($lessons as $idx => $lesson): ?>
    <?php
      $prog   = $progressMap[$lesson['id']] ?? null;
      $done   = $prog && $prog['done'];
      $locked = !$lesson['is_open'];
      $gameIcon = ['mountain' => '⛰️', 'maze' => '🌀', 'ship' => '⛵'][$lesson['game_type']] ?? '🎮';
    ?>
    <div class="card" style="display:flex;align-items:center;gap:1.25rem;padding:1.25rem;<?= $locked ? 'opacity:.65;' : '' ?>">
      <div style="width:52px;height:52px;background:<?= $done ? 'var(--primary)' : ($locked ? 'var(--border)' : '#E8F5E9') ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
        <?= $locked ? '🔒' : ($done ? '✅' : ($idx + 1)) ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:1.05rem;"><?= clean($lesson['name']) ?></div>
        <?php if ($lesson['description']): ?>
        <div style="font-size:.88rem;color:var(--muted);"><?= clean($lesson['description']) ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:.75rem;margin-top:.4rem;font-size:.82rem;color:var(--muted);">
          <?php if ($lesson['pdf_url']): ?>     <span><i class="fas fa-file-pdf" style="color:#E53935;"></i> عرض</span>   <?php endif; ?>
          <?php if ($lesson['podcast_url']): ?> <span><i class="fas fa-podcast"  style="color:var(--info);"></i> بودكاست</span> <?php endif; ?>
          <?php if ($lesson['video_url']): ?>   <span><i class="fas fa-video"    style="color:var(--accent);"></i> فيديو</span> <?php endif; ?>
          <span><?= $gameIcon ?> لعبة</span>
          <?php if ($prog): ?><span style="color:var(--accent);font-weight:700;"><i class="fas fa-star"></i> <?= $prog['pts'] ?> نقطة</span><?php endif; ?>
        </div>
      </div>
      <?php if (!$locked): ?>
      <a href="/student/lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-play"></i> ابدأ
      </a>
      <?php else: ?>
      <span class="badge badge-danger">مغلق</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <!-- ---- All courses ---- -->
  <h2 style="margin-bottom:1.5rem;"><i class="fas fa-book-open"></i> مقرراتي</h2>
  <?php if (empty($courses)): ?>
    <div class="alert alert-info"><i class="fas fa-info-circle"></i> لا توجد مقررات متاحة حالياً.</div>
  <?php else: ?>
  <div class="courses-grid">
    <?php foreach ($courses as $c): ?>
    <a href="/student/courses.php?id=<?= $c['id'] ?>" style="text-decoration:none;">
      <div class="course-card">
        <div class="course-banner" style="background:linear-gradient(135deg,<?= clean($c['color']) ?>,<?= clean($c['color']) ?>99);">
          <i class="fas <?= clean($c['icon']) ?>" style="color:#fff;font-size:3rem;"></i>
        </div>
        <div class="course-body">
          <div class="course-title"><?= clean($c['name']) ?></div>
          <div class="course-meta"><span><i class="fas fa-layer-group"></i> <?= $c['lessons_count'] ?> درس</span></div>
          <?php if ($c['description']): ?>
          <p style="font-size:.85rem;color:var(--muted);margin-top:.5rem;"><?= clean($c['description']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</main>

<!-- Chatbot -->
<button class="chatbot-toggle" id="chatbotToggle"><i class="fas fa-robot"></i></button>
<div class="chatbot-box" id="chatbotBox">
  <div class="chatbot-header"><i class="fas fa-robot"></i><span class="chatbot-title">المساعد الذكي</span><button class="chatbot-close" id="chatbotClose"><i class="fas fa-times"></i></button></div>
  <div class="chatbot-msgs" id="chatbotMsgs"></div>
  <form class="chatbot-input" id="chatbotForm"><input type="text" id="chatbotInput" placeholder="اكتب سؤالك…" autocomplete="off"><button type="submit"><i class="fas fa-paper-plane"></i></button></form>
</div>

<script src="/assets/js/app.js"></script>
<script>if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';</script>
</body>
</html>

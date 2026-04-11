<?php
require_once __DIR__ . '/../includes/functions.php';
$student = requireStudent();
$db      = getDB();

// Scholars discovered
$myScholars = $db->prepare("SELECT s.* FROM scholars s JOIN student_scholars ss ON ss.scholar_id = s.id WHERE ss.student_id = ? ORDER BY ss.discovered_at DESC");
$myScholars->execute([$student['id']]);
$myScholars = $myScholars->fetchAll();

// All scholars (for display)
$allScholars = $db->query("SELECT id, name, era FROM scholars ORDER BY id")->fetchAll();
$discovered  = array_column($myScholars, 'id');

// Game history
$games = $db->prepare("SELECT sg.*, l.name AS lesson_name, sc.name AS scholar_name FROM student_games sg LEFT JOIN lessons l ON l.id = sg.lesson_id LEFT JOIN scholars sc ON sc.id = sg.scholar_id WHERE sg.student_id = ? ORDER BY sg.played_at DESC LIMIT 20");
$games->execute([$student['id']]);
$games = $games->fetchAll();

$rank = $db->query("SELECT COUNT(*) + 1 FROM students WHERE points > {$student['points']}")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>نقاطي وعلمائي – المساعد الذّكاليّ</title>
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
  <a href="/student/courses.php"   class="sidebar-link"><i class="fas fa-book-open"></i> مقرراتي</a>
  <a href="/student/profile.php"   class="sidebar-link active"><i class="fas fa-trophy"></i> نقاطي وعلمائي</a>
</aside>
<main class="main-content">
  <h2 style="margin-bottom:1.5rem;"><i class="fas fa-trophy" style="color:var(--accent);"></i> نقاطي وعلمائي</h2>

  <!-- Stats -->
  <div class="stats-grid" style="margin-bottom:2rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF8E1;"><i class="fas fa-star" style="color:#F9A825;"></i></div>
      <div class="stat-value"><?= number_format($student['points']) ?></div>
      <div class="stat-label">مجموع النقاط</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8EAF6;"><i class="fas fa-medal" style="color:#3F51B5;"></i></div>
      <div class="stat-value"><?= $rank ?></div>
      <div class="stat-label">ترتيبي</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-scroll" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= count($myScholars) ?></div>
      <div class="stat-label">علماء مُكتشفون</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-gamepad" style="color:#C2185B;"></i></div>
      <div class="stat-value"><?= count(array_filter($games, fn($g) => $g['completed'])) ?></div>
      <div class="stat-label">مغامرة مكتملة</div>
    </div>
  </div>

  <!-- Scholars collection -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-scroll"></i> مجموعة علماء النحو (<?= count($myScholars) ?>/<?= count($allScholars) ?>)</div>
    </div>
    <div class="scholars-grid">
      <?php foreach ($allScholars as $sc): ?>
      <?php $isDiscovered = in_array($sc['id'], $discovered); ?>
      <div class="scholar-chip <?= $isDiscovered ? '' : 'locked' ?>" title="<?= $isDiscovered ? clean($sc['name']) : 'لم يُكتشف بعد' ?>">
        <div class="icon">📜</div>
        <div class="sname"><?= $isDiscovered ? clean($sc['name']) : '??? مجهول' ?></div>
        <div class="sera"><?= $isDiscovered ? clean($sc['era'] ?? '') : '🔒' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Discovered scholars detail -->
  <?php if (!empty($myScholars)): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-book"></i> العلماء الذين اكتشفتهم</div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;">
      <?php foreach ($myScholars as $sc): ?>
      <div class="scholar-card">
        <div class="scholar-img">📜</div>
        <div class="scholar-name"><?= clean($sc['name']) ?></div>
        <?php if ($sc['era']): ?>
        <div style="font-size:.8rem;opacity:.75;margin:.25rem 0;"><?= clean($sc['era']) ?></div>
        <?php endif; ?>
        <div class="scholar-bio"><?= clean($sc['short_bio']) ?></div>
        <?php if ($sc['works']): ?>
        <div style="font-size:.82rem;margin-top:.5rem;opacity:.8;"><strong>أبرز مؤلفاته:</strong> <?= clean($sc['works']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Game history -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-history"></i> سجل المغامرات</div>
    </div>
    <?php if (empty($games)): ?>
    <p style="color:var(--muted);">لم تخوض أي مغامرة بعد. ادخل أي درس وابدأ!</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الدرس</th><th>النقاط</th><th>العالم المكتشف</th><th>المحاولات</th><th>النتيجة</th><th>التاريخ</th></tr></thead>
        <tbody>
          <?php foreach ($games as $g): ?>
          <tr>
            <td><?= clean($g['lesson_name'] ?? '-') ?></td>
            <td><strong style="color:var(--accent);">+<?= $g['points_earned'] ?></strong></td>
            <td><?= clean($g['scholar_name'] ?? '-') ?></td>
            <td><?= $g['attempts'] ?></td>
            <td><?= $g['completed'] ? '<span class="badge badge-primary">✅ فوز</span>' : '<span class="badge badge-danger">❌ لم يكتمل</span>' ?></td>
            <td style="font-size:.82rem;"><?= date('d/m/Y', strtotime($g['played_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
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

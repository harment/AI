<?php
require_once __DIR__ . '/../includes/functions.php';
$student = requireStudent();
$db      = getDB();

// Scholars discovered
$myScholars = $db->prepare("SELECT s.* FROM scholars s JOIN student_scholars ss ON ss.scholar_id = s.id WHERE ss.student_id = ? ORDER BY ss.discovered_at DESC");
$myScholars->execute([$student['id']]);
$myScholars = $myScholars->fetchAll();

// All scholars (for display + modal details)
$allScholars = $db->query("SELECT id, name, era, short_bio, works FROM scholars ORDER BY id")->fetchAll();
$discovered  = array_column($myScholars, 'id');

// Game history
$games = $db->prepare("SELECT sg.*, l.name AS lesson_name, sc.name AS scholar_name FROM student_games sg LEFT JOIN lessons l ON l.id = sg.lesson_id LEFT JOIN scholars sc ON sc.id = sg.scholar_id WHERE sg.student_id = ? ORDER BY sg.played_at DESC LIMIT 20");
$games->execute([$student['id']]);
$games = $games->fetchAll();

$gameSummary = $db->prepare("
  SELECT 
    COUNT(*) AS total_games,
    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) AS completed_games,
    SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) AS incomplete_games
  FROM student_games
  WHERE student_id = ?
");
$gameSummary->execute([$student['id']]);
$gameSummary = $gameSummary->fetch() ?: ['total_games' => 0, 'completed_games' => 0, 'incomplete_games' => 0];

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
      <div class="stat-value"><?= (int)$gameSummary['completed_games'] ?></div>
      <div class="stat-label">مغامرات ناجحة 100%</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFEBEE;"><i class="fas fa-times-circle" style="color:var(--danger);"></i></div>
      <div class="stat-value"><?= (int)$gameSummary['incomplete_games'] ?></div>
      <div class="stat-label">مغامرات غير مكتملة 100%</div>
    </div>
  </div>

  <!-- Scholars collection -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-scroll"></i> مجموعة علماء العربية (<?= count($myScholars) ?>/<?= count($allScholars) ?>)</div>
    </div>
    <div class="scholars-grid">
      <?php foreach ($allScholars as $sc): ?>
      <?php $isDiscovered = in_array($sc['id'], $discovered); ?>
      <?php if ($isDiscovered): ?>
      <div class="scholar-chip" title="<?= clean($sc['name']) ?>" style="cursor:pointer;" onclick="openScholarModal(<?= (int)$sc['id'] ?>)">
        <div class="icon">📜</div>
        <div class="sname"><?= clean($sc['name']) ?></div>
        <div class="sera"><?= clean($sc['era'] ?? '') ?></div>
      </div>
      <?php else: ?>
      <div class="scholar-chip locked" title="لم يُكتشف بعد">
        <div class="icon">📜</div>
        <div class="sname">??? مجهول</div>
        <div class="sera">🔒</div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

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
            <td><?= $g['completed'] ? '<span class="badge badge-primary">✅ مكتملة 100%</span>' : '<span class="badge badge-danger">❌ غير مكتملة 100%</span>' ?></td>
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

<!-- Scholar Details Modal -->
<div id="scholarModal" class="modal-backdrop" style="display:none;">
  <div class="modal-box" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title" id="smName"></span>
      <button class="modal-close" onclick="document.getElementById('scholarModal').style.display='none'"><i class="fas fa-times"></i></button>
    </div>
    <div style="text-align:center;margin-bottom:1rem;">
      <div style="font-size:3.5rem;margin-bottom:.5rem;">📜</div>
      <div id="smEra" style="font-size:.85rem;color:var(--muted);"></div>
    </div>
    <div id="smBio" style="margin-bottom:.85rem;line-height:1.8;color:var(--text);"></div>
    <div id="smWorks" style="font-size:.88rem;padding:.75rem;background:#E8F5E9;border-radius:var(--radius-sm);color:#2E7D32;display:none;">
      <strong><i class="fas fa-book-open"></i> أبرز مؤلفاته:</strong>
      <span id="smWorksText"></span>
    </div>
    <div style="margin-top:1.25rem;text-align:center;">
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('scholarModal').style.display='none'">إغلاق</button>
    </div>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

const scholarsData = <?= json_encode(
  array_filter(
    array_map(fn($sc) => in_array($sc['id'], $discovered) ? [
      'id'        => (int)$sc['id'],
      'name'      => $sc['name'],
      'era'       => $sc['era'] ?? '',
      'short_bio' => $sc['short_bio'] ?? '',
      'works'     => $sc['works'] ?? ''
    ] : null, $allScholars)
  ),
  JSON_UNESCAPED_UNICODE
) ?>;

function openScholarModal(id) {
  const sc = Object.values(scholarsData).find(s => s && s.id === id);
  if (!sc) return;
  document.getElementById('smName').textContent = sc.name;
  document.getElementById('smEra').textContent   = sc.era ? '📅 ' + sc.era : '';
  document.getElementById('smBio').textContent   = sc.short_bio;
  const worksEl = document.getElementById('smWorks');
  if (sc.works) {
    document.getElementById('smWorksText').textContent = ' ' + sc.works;
    worksEl.style.display = 'block';
  } else {
    worksEl.style.display = 'none';
  }
  document.getElementById('scholarModal').style.display = 'flex';
}

document.getElementById('scholarModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>

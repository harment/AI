<?php
require_once __DIR__ . '/../includes/functions.php';
$student  = requireStudent();
$db       = getDB();
$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$lessonId) { header('Location: /student/courses.php'); exit; }

$lesson = $db->prepare("SELECT l.*, c.name AS course_name, c.id AS course_id FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ? AND l.is_open = 1");
$lesson->execute([$lessonId]);
$lesson = $lesson->fetch();
if (!$lesson) { header('Location: /student/courses.php'); exit; }

// Questions for the game
$questions = $db->prepare("SELECT * FROM questions WHERE lesson_id = ? ORDER BY sort_order, id");
$questions->execute([$lessonId]);
$questions = $questions->fetchAll();

// Scholars for game rewards
$scholars = $db->query("SELECT id, name, short_bio FROM scholars ORDER BY RAND() LIMIT 7")->fetchAll();

// Log view
logActivity($student['id'], $lessonId, 'lesson_view', $lesson['name']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= clean($lesson['name']) ?> – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body data-lesson-id="<?= $lessonId ?>">
<nav class="navbar">
  <div class="navbar-brand">
    <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button>
    <span>🌿</span><span>المساعد الذّكاليّ</span>
  </div>
  <ul class="navbar-nav">
    <li><a href="/student/courses.php?id=<?= $lesson['course_id'] ?>" class="nav-link"><i class="fas fa-arrow-right"></i> رجوع</a></li>
    <li><a href="/api/auth.php?action=logout" class="nav-link"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
  </ul>
</nav>
<aside class="sidebar">
  <a href="/student/dashboard.php" class="sidebar-link"><i class="fas fa-home"></i> الرئيسية</a>
  <a href="/student/courses.php"   class="sidebar-link"><i class="fas fa-book-open"></i> مقرراتي</a>
  <a href="/student/profile.php"   class="sidebar-link"><i class="fas fa-trophy"></i> نقاطي وعلمائي</a>
</aside>
<main class="main-content">
  <!-- Breadcrumb -->
  <div style="margin-bottom:1rem;font-size:.88rem;color:var(--muted);">
    <a href="/student/courses.php">مقرراتي</a> /
    <a href="/student/courses.php?id=<?= $lesson['course_id'] ?>"><?= clean($lesson['course_name']) ?></a> /
    <strong style="color:var(--text);"><?= clean($lesson['name']) ?></strong>
  </div>

  <h2 style="margin-bottom:1.5rem;"><?= clean($lesson['name']) ?></h2>

  <!-- Tabs -->
  <div class="lesson-tabs" data-tabs>
    <button class="tab-btn active" data-tab-target="tabPresentation"><i class="fas fa-file-powerpoint" style="color:#E53935;"></i> العرض التقديمي</button>
    <button class="tab-btn" data-tab-target="tabPodcast"><i class="fas fa-podcast" style="color:var(--info);"></i> البودكاست</button>
    <button class="tab-btn" data-tab-target="tabVideo"><i class="fas fa-video" style="color:var(--accent);"></i> الفيديو التعليمي</button>
    <button class="tab-btn" data-tab-target="tabGame"><i class="fas fa-gamepad" style="color:var(--primary);"></i> لعبة المغامرة</button>
  </div>

  <!-- Tab: Presentation PDF -->
  <div id="tabPresentation" class="tab-pane active">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-file-pdf" style="color:#E53935;"></i> العرض التقديمي</div>
      </div>
      <?php if ($lesson['pdf_url']): ?>
        <iframe src="<?= clean($lesson['pdf_url']) ?>" class="pdf-frame" title="عرض تقديمي"></iframe>
      <?php elseif ($lesson['presentation_html']): ?>
        <div style="padding:1rem;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:auto;max-height:600px;">
          <?= $lesson['presentation_html'] ?>
        </div>
      <?php else: ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> لم يُضَف العرض التقديمي بعد.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Podcast -->
  <div id="tabPodcast" class="tab-pane">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-podcast" style="color:var(--info);"></i> البودكاست الصوتي الحواري</div>
      </div>
      <?php if ($lesson['podcast_url']): ?>
        <div class="audio-player">
          <div style="font-size:1.05rem;font-weight:600;margin-bottom:.5rem;"><i class="fas fa-headphones"></i> استمع إلى درس: <?= clean($lesson['name']) ?></div>
          <audio controls preload="metadata">
            <source src="<?= clean($lesson['podcast_url']) ?>">
            متصفحك لا يدعم تشغيل الصوت.
          </audio>
        </div>
      <?php else: ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> لم يُضَف البودكاست بعد.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Video -->
  <div id="tabVideo" class="tab-pane">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-video" style="color:var(--accent);"></i> الفيديو التعليمي</div>
      </div>
      <?php if ($lesson['video_url']): ?>
        <?php if (str_contains($lesson['video_url'], 'youtube') || str_contains($lesson['video_url'], 'youtu.be')): ?>
          <iframe src="<?= clean($lesson['video_url']) ?>" class="video-frame" allowfullscreen allow="autoplay; encrypted-media"></iframe>
        <?php else: ?>
          <video controls class="video-frame">
            <source src="<?= clean($lesson['video_url']) ?>">
            متصفحك لا يدعم تشغيل الفيديو.
          </video>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> لم يُضَف الفيديو التعليمي بعد.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Game -->
  <div id="tabGame" class="tab-pane">
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div class="card-title">
          <?php
            $gameIcons = ['mountain' => '⛰️ مغامرة الجبل', 'maze' => '🌀 مغامرة المتاهة', 'ship' => '⛵ مغامرة البحر'];
            echo $gameIcons[$lesson['game_type']] ?? '🎮 لعبة المغامرة';
          ?>
        </div>
        <?php if (!empty($questions)): ?>
        <button class="btn btn-primary btn-sm" id="startGameBtn"><i class="fas fa-play"></i> ابدأ المغامرة</button>
        <?php endif; ?>
      </div>

      <?php if (empty($questions)): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> لم تُضَف أسئلة لهذا الدرس بعد.</div>
      <?php else: ?>
      <!-- Game instructions -->
      <div id="gameIntro" style="text-align:center;padding:2rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">
          <?= ['mountain' => '⛰️', 'maze' => '🌀', 'ship' => '⛵'][$lesson['game_type']] ?? '🎮' ?>
        </div>
        <h3 style="margin-bottom:.75rem;">مغامرة <?= clean($lesson['name']) ?></h3>
        <p style="color:var(--muted);max-width:480px;margin:0 auto 1.5rem;">
          أجب على <strong><?= count($questions) ?></strong> سؤال لتصعد إلى القمة واكسب النقاط واكتشف عالماً من علماء النحو!
          <br>تحذير: لكل سؤال محاولتان – ستظهر لك التغذية الراجعة الصحيحة عند الخطأ مرتين.
        </p>
        <div style="display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap;">
          <div class="badge badge-primary"><i class="fas fa-star"></i> حتى 350 نقطة</div>
          <div class="badge badge-accent"><i class="fas fa-scroll"></i> اكتشف عالم نحو</div>
          <div class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> محاولتان لكل سؤال</div>
        </div>
        <button class="btn btn-primary btn-lg" style="margin-top:2rem;" onclick="launchGame()">
          <i class="fas fa-rocket"></i> ابدأ المغامرة!
        </button>
      </div>
      <div id="gameContainer" class="game-container" style="display:none;"></div>
      <?php endif; ?>
    </div>
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
<script src="/assets/js/game.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

const QUESTIONS = <?= json_encode($questions, JSON_UNESCAPED_UNICODE) ?>;
const SCHOLARS  = <?= json_encode($scholars,  JSON_UNESCAPED_UNICODE) ?>;
const LESSON_ID = <?= $lessonId ?>;
const GAME_TYPE = '<?= $lesson['game_type'] ?>';

let adventureGame = null;
window.adventureGame = null;

function launchGame() {
  document.getElementById('gameIntro').style.display    = 'none';
  document.getElementById('gameContainer').style.display = 'flex';
  adventureGame = new AdventureGame({
    lessonId: LESSON_ID,
    gameType: GAME_TYPE,
    questions: AdventureGame.shuffle(QUESTIONS),
    scholars: SCHOLARS,
    containerId: 'gameContainer',
  });
  window.adventureGame = adventureGame;
  adventureGame.start();
}

document.getElementById('startGameBtn')?.addEventListener('click', () => {
  document.querySelector('[data-tab-target="tabGame"]')?.click();
  setTimeout(launchGame, 100);
});
</script>
</body>
</html>

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

// ========== معالجة العرض التقديمي ==========
$presentationType = 'none';
$presentationUrl = '';

if (!empty($lesson['presentation_html'])) {
    $htmlData = @json_decode($lesson['presentation_html'], true);
    
    if ($htmlData && isset($htmlData['type'])) {
        if ($htmlData['type'] === 'gamma') {
            $presentationType = 'gamma_pdf';
            if (!empty($lesson['presentation_pdf'])) {
                $presentationUrl = $lesson['presentation_pdf'];
            } elseif (!empty($htmlData['export_url'])) {
                $presentationUrl = $htmlData['export_url'];
            } elseif (!empty($htmlData['gamma_url'])) {
                $presentationUrl = $htmlData['gamma_url'];
            }
        } elseif ($htmlData['type'] === 'html' && !empty($htmlData['html_url'])) {
            $presentationType = 'html_interactive';
            $presentationUrl = $htmlData['html_url'];
        }
    }
}

if ($presentationType === 'none' && !empty($lesson['pdf_url'])) {
    $presentationType = 'original_pdf';
    $presentationUrl = $lesson['pdf_url'];
}

// ========== معالجة الفيديو ==========
$videoType = 'none';
$videoEmbedUrl = '';
$isVideoProcessing = false;

if (!empty($lesson['video_url'])) {
    $videoData = @json_decode($lesson['video_url'], true);
    
    if ($videoData && is_array($videoData)) {
        // JSON - فيديو من HeyGen
        $status = $videoData['status'] ?? '';
        
        if ($status === 'processing') {
            $isVideoProcessing = true;
        } elseif ($status === 'completed') {
            $videoType = 'heygen';
            $videoId = $videoData['video_id'] ?? '';
            
            // استخدام embed_url المحفوظ أو بناء الرابط
            if (!empty($videoData['embed_url'])) {
                $videoEmbedUrl = $videoData['embed_url'];
            } elseif (!empty($videoId)) {
                $videoEmbedUrl = "https://app.heygen.com/embeds/" . $videoId;
            }
        }
    } else {
        // نص عادي - رابط مباشر
        $rawUrl = trim($lesson['video_url']);
        
        if (str_contains($rawUrl, 'youtube.com') || str_contains($rawUrl, 'youtu.be')) {
            $videoType = 'youtube';
            
            // تحويل لرابط embed
            if (str_contains($rawUrl, 'watch?v=')) {
                $videoEmbedUrl = str_replace('watch?v=', 'embed/', $rawUrl);
            } elseif (str_contains($rawUrl, 'youtu.be/')) {
                $videoEmbedUrl = str_replace('youtu.be/', 'youtube.com/embed/', $rawUrl);
            } else {
                $videoEmbedUrl = $rawUrl;
            }
        } elseif (str_contains($rawUrl, 'heygen.com')) {
            $videoType = 'heygen';
            
            // تحويل /videos/ إلى /embeds/
            if (str_contains($rawUrl, '/videos/')) {
                $videoEmbedUrl = str_replace('/videos/', '/embeds/', $rawUrl);
            } else {
                $videoEmbedUrl = $rawUrl;
            }
        } else {
            $videoType = 'local';
            $videoEmbedUrl = $rawUrl;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= clean($lesson['name']) ?> – المساعد الذّكاليّ</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  .interactive-presentation-frame {
    width: 100%;
    min-height: 700px;
    border: none;
    border-radius: var(--radius-sm);
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .video-embed-container {
    position: relative;
    width: 100%;
    max-width: 1280px; /* عرض 720p */
    margin: 0 auto; /* توسيط */
    aspect-ratio: 16 / 9; /* نسبة 16:9 */
    background: #000;
    border-radius: var(--radius-sm);
    overflow: hidden;
  }
  .video-embed-frame {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
  }
  @media (max-width: 768px) {
    .video-embed-container {
      max-width: 100%;
    }
  }
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
  </style>
</head>
<body data-lesson-id="<?= $lessonId ?>">
<nav class="navbar">
  <div class="navbar-brand">
    <button id="sidebarToggle" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;display:none;"><i class="fas fa-bars"></i></button>
    <span>�</span><span>المساعد الذّكاليّ</span>
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

  <!-- Tab: Presentation -->
  <div id="tabPresentation" class="tab-pane active">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-file-pdf" style="color:#E53935;"></i> العرض التقديمي</div>
      </div>
      
      <?php if ($presentationType === 'gamma_pdf' && !empty($presentationUrl)): ?>
        <iframe src="<?= clean($presentationUrl) ?>" class="pdf-frame" title="عرض تقديمي من Gamma"></iframe>
        
      <?php elseif ($presentationType === 'html_interactive' && !empty($presentationUrl)): ?>
        <iframe src="<?= clean($presentationUrl) ?>" class="interactive-presentation-frame" title="عرض تقديمي تفاعلي"></iframe>
        <div class="alert alert-info" style="margin:1rem;">
          <i class="fas fa-hand-pointer"></i> عرض تفاعلي: يمكنك النقر على الأزرار والتنقل بين الشرائح
        </div>
        
      <?php elseif ($presentationType === 'original_pdf' && !empty($presentationUrl)): ?>
        <iframe src="<?= clean($presentationUrl) ?>" class="pdf-frame" title="ملف الدرس"></iframe>
        <div class="alert alert-info" style="margin:1rem;">
          <i class="fas fa-info-circle"></i> يتم عرض ملف الدرس الأصلي. لم يتم توليد عرض تقديمي خاص بعد.
        </div>
        
      <?php else: ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i> لم يُضَف العرض التقديمي بعد.
        </div>
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
          <div style="font-size:1.05rem;font-weight:600;margin-bottom:.5rem;">
            <i class="fas fa-headphones"></i> استمع إلى درس: <?= clean($lesson['name']) ?>
          </div>
          <audio controls preload="metadata" style="width:100%;">
            <source src="<?= clean($lesson['podcast_url']) ?>">
            متصفحك لا يدعم تشغيل الصوت.
          </audio>
        </div>
      <?php else: ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i> لم يُضَف البودكاست بعد.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Video -->
  <div id="tabVideo" class="tab-pane">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-video" style="color:var(--accent);"></i> الفيديو التعليمي</div>
      </div>
      
      <?php if ($isVideoProcessing): ?>
        <div class="alert alert-info">
          <div style="display:flex;align-items:center;gap:1rem;">
            <div class="spinner" style="width:24px;height:24px;border:3px solid #0288d1;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;flex-shrink:0;"></div>
            <div>
              <strong>جارٍ توليد الفيديو بواسطة الذكاء الاصطناعي...</strong><br>
              <small>قد يستغرق الأمر 15-20 دقيقة. سيتم تحديث الصفحة تلقائياً عند الانتهاء.</small>
            </div>
          </div>
        </div>
        
      <?php elseif ($videoType === 'youtube' && !empty($videoEmbedUrl)): ?>
        <div class="video-embed-container">
          <iframe 
            src="<?= clean($videoEmbedUrl) ?>" 
            class="video-embed-frame" 
            allowfullscreen 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            title="<?= clean($lesson['name']) ?>">
          </iframe>
        </div>
        <div class="alert alert-info" style="margin-top:1rem;">
          <i class="fab fa-youtube" style="color:#FF0000;"></i> فيديو من YouTube
        </div>
        
      <?php elseif ($videoType === 'heygen' && !empty($videoEmbedUrl)): ?>
        <div class="video-embed-container">
          <iframe 
            src="<?= clean($videoEmbedUrl) ?>" 
            class="video-embed-frame"
            allow="encrypted-media; fullscreen" 
            allowfullscreen
            title="<?= clean($lesson['name']) ?>">
          </iframe>
        </div>
        <div class="alert alert-success" style="margin-top:1rem;">
          <i class="fas fa-robot" style="color:#4CAF50;"></i> <strong>تم توليد هذا الفيديو بواسطة الذكاء الاصطناعي (HeyGen)</strong>
          <br><small>مقدم افتراضي • صوت عربي طبيعي • مُولّد تلقائياً من محتوى الدرس</small>
        </div>
        
      <?php elseif ($videoType === 'local' && !empty($videoEmbedUrl)): ?>
        <div class="video-embed-container">
          <video controls class="video-embed-frame">
            <source src="<?= clean($videoEmbedUrl) ?>">
            متصفحك لا يدعم تشغيل الفيديو.
          </video>
        </div>
        
      <?php else: ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i> لم يُضَف الفيديو التعليمي بعد.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Game -->
  <div id="tabGame" class="tab-pane">
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div class="card-title">
          <?php
            $gameIcons = [
              'mountain' => '⛰️ مغامرة الجبل',
              'maze' => '� مغامرة المتاهة',
              'ship' => '⛵ مغامرة البحر'
            ];
            echo $gameIcons[$lesson['game_type']] ?? '� لعبة المغامرة';
          ?>
        </div>
        <?php if (!empty($questions)): ?>
        <button class="btn btn-primary btn-sm" id="startGameBtn">
          <i class="fas fa-play"></i> ابدأ المغامرة
        </button>
        <?php endif; ?>
      </div>

      <?php if (empty($questions)): ?>
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i> لم تُضَف أسئلة لهذا الدرس بعد.
        </div>
      <?php else: ?>
      <div id="gameIntro" style="text-align:center;padding:2rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">
          <?= ['mountain' => '⛰️', 'maze' => '�', 'ship' => '⛵'][$lesson['game_type']] ?? '�' ?>
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
  <div class="chatbot-header">
    <i class="fas fa-robot"></i>
    <span class="chatbot-title">المساعد الذكي</span>
    <button class="chatbot-close" id="chatbotClose"><i class="fas fa-times"></i></button>
  </div>
  <div class="chatbot-msgs" id="chatbotMsgs"></div>
  <form class="chatbot-input" id="chatbotForm">
    <input type="text" id="chatbotInput" placeholder="اكتب سؤالك…" autocomplete="off">
    <button type="submit"><i class="fas fa-paper-plane"></i></button>
  </form>
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

function syncLessonGameUIState() {
  const gameTab = document.getElementById('tabGame');
  const isGameTabActive = !!gameTab && gameTab.classList.contains('active');
  document.body.classList.toggle('lesson-game-active', isGameTabActive);
}

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
  syncLessonGameUIState();
}

document.getElementById('startGameBtn')?.addEventListener('click', () => {
  document.querySelector('[data-tab-target="tabGame"]')?.click();
  setTimeout(launchGame, 100);
});

document.querySelectorAll('[data-tab-target]').forEach(btn => {
  btn.addEventListener('click', () => setTimeout(syncLessonGameUIState, 0));
});
syncLessonGameUIState();

<?php if ($isVideoProcessing): ?>
// التحقق التلقائي من اكتمال الفيديو
let checkAttempts = 0;
const maxAttempts = 80;

const videoCheckInterval = setInterval(() => {
  checkAttempts++;
  
  fetch(window.location.href)
    .then(response => response.text())
    .then(html => {
      const stillProcessing = html.includes('جارٍ توليد الفيديو');
      
      if (!stillProcessing) {
        clearInterval(videoCheckInterval);
        location.reload();
      }
      
      if (checkAttempts >= maxAttempts) {
        clearInterval(videoCheckInterval);
      }
    })
    .catch(() => {
      // استمر في المحاولة
    });
}, 15000);

window.addEventListener('beforeunload', () => {
  clearInterval(videoCheckInterval);
});
<?php endif; ?>
</script>
</body>
</html>

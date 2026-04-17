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
  <link rel="stylesheet" href="/assets/css/game-enhanced.css?v=4">
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
          <i class="fas fa-gamepad" style="color:var(--accent);"></i> لعبة المغامرة التعليمية
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
      
      <!-- واجهة اختيار نمط اللعبة -->
      <div id="gameModeSelector" style="padding:2rem;max-width:1000px;margin:0 auto;">
        <div style="text-align:center;margin-bottom:2rem;">
          <h2 style="font-size:2rem;color:var(--primary);margin-bottom:0.5rem;">🎮 اختر مغامرتك التعليمية</h2>
          <p style="color:var(--muted);font-size:1.1rem;">اختر خريطة المغامرة التي تناسبك</p>
        </div>
        
        <!-- ألعاب الخريطة البسيطة -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-bottom:2rem;">
          <!-- خريطة الجزيرة -->
          <div class="game-mode-card" data-mode="map-island" onclick="selectGameMode('map-island')">
            <div style="font-size:4rem;margin-bottom:1rem;">🏝️</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--primary);margin-bottom:0.5rem;">مغامرة الجزيرة</div>
            <div style="color:var(--muted);margin-bottom:0.5rem;font-size:0.9rem;">استكشف الجزيرة عبر 5 نقاط</div>
            <div style="font-size:0.85rem;color:#10b981;font-weight:600;margin-top:0.5rem;">✨ مغامرة ممتعة</div>
          </div>
          
          <!-- خريطة الجبل -->
          <div class="game-mode-card" data-mode="map-mountain" onclick="selectGameMode('map-mountain')">
            <div style="font-size:4rem;margin-bottom:1rem;">⛰️</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--primary);margin-bottom:0.5rem;">مغامرة الجبل</div>
            <div style="color:var(--muted);margin-bottom:0.5rem;font-size:0.9rem;">اصعد إلى القمة عبر 5 محطات</div>
            <div style="font-size:0.85rem;color:#10b981;font-weight:600;margin-top:0.5rem;">✨ تحدي الصعود</div>
          </div>
          
          <!-- خريطة البحيرة -->
          <div class="game-mode-card" data-mode="map-lake" onclick="selectGameMode('map-lake')">
            <div style="font-size:4rem;margin-bottom:1rem;">⛵</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--primary);margin-bottom:0.5rem;">مغامرة البحر</div>
            <div style="color:var(--muted);margin-bottom:0.5rem;font-size:0.9rem;">أبحر في البحر عبر 5 نقاط</div>
            <div style="font-size:0.85rem;color:#10b981;font-weight:600;margin-top:0.5rem;">✨ رحلة بحرية</div>
          </div>
          
          <!-- خريطة الغابة -->
          <div class="game-mode-card" data-mode="map-forest" onclick="selectGameMode('map-forest')">
            <div style="font-size:4rem;margin-bottom:1rem;">🌲</div>
            <div style="font-size:1.3rem;font-weight:700;color:var(--primary);margin-bottom:0.5rem;">مغامرة الغابة</div>
            <div style="color:var(--muted);margin-bottom:0.5rem;font-size:0.9rem;">اكتشف الغابة عبر 5 نقاط</div>
            <div style="font-size:0.85rem;color:#10b981;font-weight:600;margin-top:0.5rem;">✨ طريق الحكمة</div>
          </div>
        </div>
        
        <div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:12px;padding:1.5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;color:white;">
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-question-circle" style="font-size:1.5rem;"></i>
            <span><strong>5 أسئلة</strong> في كل مغامرة</span>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-redo" style="font-size:1.5rem;"></i>
            <span><strong>5 محاولات</strong> يومياً</span>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-star" style="font-size:1.5rem;"></i>
            <span>حتى <strong>350 نقطة</strong></span>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-user-graduate" style="font-size:1.5rem;"></i>
            <span>اكتشف <strong>العلماء</strong></span>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-check-circle" style="font-size:1.5rem;"></i>
            <span><strong>مكتملة 100%</strong>: حل جميع الأسئلة (5/5)</span>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-times-circle" style="font-size:1.5rem;"></i>
            <span><strong>غير مكتملة 100%</strong>: أي نتيجة أقل من 5/5</span>
          </div>
        </div>
        
        <div id="attemptsWarning" class="alert alert-warning" style="display:none;margin-top:1rem;">
          <i class="fas fa-exclamation-triangle"></i>
          <strong>تنبيه:</strong> <span id="attemptsMessage"></span>
        </div>
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
<script src="/assets/js/game-enhanced.js?v=4"></script>
<script src="/assets/js/game-map-simple.js?v=4"></script>
<style>
.game-mode-card {
  background: white;
  border: 2px solid #e0e0e0;
  border-radius: 16px;
  padding: 1.5rem;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
}

.game-mode-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
  border-color: var(--primary);
}
</style>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

const QUESTIONS = <?= json_encode($questions, JSON_UNESCAPED_UNICODE) ?>;
const SCHOLARS  = <?= json_encode($scholars,  JSON_UNESCAPED_UNICODE) ?>;
const LESSON_ID = <?= $lessonId ?>;

let adventureGame = null;
window.adventureGame = null;
let selectedGameMode = 'mountain';

// التحقق من المحاولات المتاحة عند تحميل الصفحة
async function checkAttempts() {
  try {
    const response = await fetch(`/api/games-enhanced.php?lesson_id=${LESSON_ID}`);
    const data = await response.json();
    
    const warningBox = document.getElementById('attemptsWarning');
    const warningMsg = document.getElementById('attemptsMessage');
    
    if (!warningBox || !warningMsg) return true;
    
    if (data.can_play) {
      if (data.attempts_remaining <= 1) {
        warningBox.style.display = 'block';
        warningBox.className = 'alert alert-warning';
        warningMsg.textContent = `هذه آخر محاولة متاحة لك اليوم!`;
      } else if (data.attempts_remaining <= 2) {
        warningBox.style.display = 'block';
        warningBox.className = 'alert alert-info';
        warningMsg.textContent = `لديك ${data.attempts_remaining} محاولة متبقية اليوم`;
      }
      return true;
    } else {
      warningBox.style.display = 'block';
      warningBox.className = 'alert alert-danger';
      const nextTime = formatNextAvailableTime(data.next_available);
      warningMsg.innerHTML = `
        <strong>لقد استنفدت محاولاتك اليومية (${data.max_attempts} محاولات)</strong><br>
        <small>يمكنك المحاولة مرة أخرى بعد: ${nextTime}</small>
      `;
      
      // تعطيل أزرار البدء
      document.querySelectorAll('.game-mode-card').forEach(card => {
        card.style.opacity = '0.5';
        card.style.cursor = 'not-allowed';
        card.onclick = null;
      });
      
      return false;
    }
  } catch (error) {
    console.error('Error checking attempts:', error);
    return true;
  }
}

function formatNextAvailableTime(timeString) {
  if (!timeString) return '';
  const nextTime = new Date(timeString);
  const now = new Date();
  const diff = nextTime - now;
  
  const hours = Math.floor(diff / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  
  if (hours > 0) {
    return `${hours} ساعة و ${minutes} دقيقة`;
  } else {
    return `${minutes} دقيقة`;
  }
}

async function selectGameMode(mode) {
  // التحقق من المحاولات المتاحة
  const canPlay = await checkAttempts();
  if (!canPlay) {
    alert('لقد استنفدت محاولاتك اليومية. يرجى المحاولة مرة أخرى لاحقاً.');
    return;
  }
  
  selectedGameMode = mode;
  
  // إخفاء واجهة الاختيار
  const selector = document.getElementById('gameModeSelector');
  if (selector) {
    selector.style.display = 'none';
  }
  
  // إظهار حاوية اللعبة
  const gameContainer = document.getElementById('gameContainer');
  if (gameContainer) {
    gameContainer.style.display = 'flex';
  }
  
  // بدء اللعبة
  launchGameWithMode(mode);
}

function launchGameWithMode(mode) {
  const allowedMapThemes = new Set(['island', 'mountain', 'lake', 'forest']);
  const safeMode = typeof mode === 'string' ? mode : 'map-mountain';

  // التحقق إذا كان النمط خريطة بسيطة
  if (safeMode.startsWith('map-')) {
    const mapThemeRaw = safeMode.replace('map-', '');
    const mapTheme = allowedMapThemes.has(mapThemeRaw) ? mapThemeRaw : 'mountain';
    adventureGame = new MapAdventureGame({
      lessonId: LESSON_ID,
      mapTheme: mapTheme,
      questions: QUESTIONS, // سيتم اختيار 5 أسئلة عشوائياً داخل الكلاس
      scholars: SCHOLARS,
      containerId: 'gameContainer',
    });
  } else {
    // استخدام اللعبة التقليدية
    adventureGame = new AdventureGame({
      lessonId: LESSON_ID,
      gameType: 'mountain',
      questions: QUESTIONS, // سيتم اختيار 5 أسئلة عشوائياً داخل الكلاس
      scholars: SCHOLARS,
      containerId: 'gameContainer',
    });
  }
  window.adventureGame = adventureGame;
  adventureGame.start();
}

window.launchGameWithMode = launchGameWithMode;

document.getElementById('startGameBtn')?.addEventListener('click', () => {
  document.querySelector('[data-tab-target="tabGame"]')?.click();
  setTimeout(() => {
    checkAttempts(); // التحقق عند فتح التبويب
  }, 100);
});

// التحقق التلقائي عند فتح تبويب اللعبة
document.querySelector('[data-tab-target="tabGame"]')?.addEventListener('click', () => {
  setTimeout(() => {
    checkAttempts();
  }, 100);
});

<?php if ($isVideoProcessing): ?>
// التحقق التلقائي من اكتمال الفيديو
let videoCheckPollCount = 0;
const maxAttempts = 80;

const videoCheckInterval = setInterval(() => {
  videoCheckPollCount++;
  
  fetch(window.location.href)
    .then(response => response.text())
    .then(html => {
      const stillProcessing = html.includes('جارٍ توليد الفيديو');
      
      if (!stillProcessing) {
        clearInterval(videoCheckInterval);
        location.reload();
      }
      
      if (videoCheckPollCount >= maxAttempts) {
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

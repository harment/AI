<?php
require_once __DIR__ . '/../includes/functions.php';
$teacher  = requireTeacher();
$db       = getDB();
$lessonId = (int)($_GET['lesson_id'] ?? 0);

if (!$lessonId) { header('Location: /teacher/lessons.php'); exit; }

$lesson = $db->prepare("SELECT l.*, c.name AS course_name FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?");
$lesson->execute([$lessonId]);
$lesson = $lesson->fetch();
if (!$lesson) { header('Location: /teacher/lessons.php'); exit; }

// ===================== AJAX: تحليل الذكاء الاصطناعي =====================
if (isset($_POST['action']) && $_POST['action'] === 'ai_analyze') {
    @ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    $apiKey  = trim($_POST['api_key'] ?? '');
    $provider = trim($_POST['provider'] ?? 'claude');
    if (empty($apiKey) && defined('ANTHROPIC_API_KEY')) $apiKey = ANTHROPIC_API_KEY;
    if (empty($apiKey) && defined('GEMINI_API_KEY'))    { $apiKey = GEMINI_API_KEY; $provider = 'gemini'; }

    if (empty($apiKey)) {
        echo json_encode(['error' => 'لم يتم تعيين مفتاح API. أدخله في الحقل أعلاه.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // جمع بيانات الأداء
    $questions = $db->prepare("SELECT * FROM questions WHERE lesson_id=? ORDER BY sort_order, id");
    $questions->execute([$lessonId]);
    $questions = $questions->fetchAll();

    $games = $db->prepare(
        "SELECT sg.student_id, s.name AS sname, sg.points_earned, sg.attempts, sg.completed, sg.played_at
         FROM student_games sg JOIN students s ON s.id=sg.student_id
         WHERE sg.lesson_id=? ORDER BY sg.played_at DESC"
    );
    $games->execute([$lessonId]);
    $games = $games->fetchAll();

    // إحصائيات السؤال التفصيلية (إذا كان جدول question_answers موجوداً)
    $hasQA = false;
    try {
        $db->query("SELECT 1 FROM question_answers LIMIT 1");
        $hasQA = true;
    } catch (Exception $e) {}

    $qaStats = [];
    if ($hasQA) {
        $qaRows = $db->prepare(
            "SELECT qa.question_id, q.question_text,
                    COUNT(*) total, SUM(qa.is_correct) correct_count
             FROM question_answers qa
             JOIN questions q ON q.id=qa.question_id
             WHERE qa.lesson_id=?
             GROUP BY qa.question_id"
        );
        $qaRows->execute([$lessonId]);
        $qaStats = $qaRows->fetchAll();
    }

    // بناء الـ prompt
    $totalGames  = count($games);
    $wins        = count(array_filter($games, fn($g) => $g['completed']));
    $winRate     = $totalGames ? round($wins / $totalGames * 100) : 0;
    $avgAttempts = $totalGames ? round(array_sum(array_column($games, 'attempts')) / $totalGames, 1) : 0;

    $promptParts = [];
    $promptParts[] = "أنت مساعد تعليمي متخصص في اللغة العربية وتحليل أداء الطلاب.";
    $promptParts[] = "\n\n## بيانات الدرس\n- اسم الدرس: {$lesson['name']}\n- المقرر: {$lesson['course_name']}\n- عدد الأسئلة: " . count($questions);
    $promptParts[] = "\n\n## إحصائيات عامة\n- إجمالي المحاولات: $totalGames\n- نسبة الفوز: $winRate%\n- متوسط المحاولات: $avgAttempts";

    if (!empty($qaStats)) {
        $promptParts[] = "\n\n## أداء كل سؤال";
        foreach ($qaStats as $qs) {
            $r = $qs['total'] ? round($qs['correct_count'] / $qs['total'] * 100) : 0;
            $promptParts[] = "- السؤال: \"{$qs['question_text']}\" – نسبة الإجابة الصحيحة: $r% ({$qs['correct_count']}/{$qs['total']})";
        }
    }

    if (!empty($games)) {
        $promptParts[] = "\n\n## أداء الطلاب (آخر 10 نتائج)";
        foreach (array_slice($games, 0, 10) as $g) {
            $status = $g['completed'] ? 'فاز' : 'لم يكمل';
            $promptParts[] = "- {$g['sname']}: {$status}، نقاط {$g['points_earned']}، محاولات {$g['attempts']}";
        }
    }

    $promptParts[] = "\n\n## المطلوب\nبناءً على البيانات أعلاه، قدّم:\n1. **تقييم عام** لأداء الطلاب في هذا الدرس (فقرة واحدة)\n2. **نقاط الضعف الرئيسية** (3 نقاط كحد أقصى)\n3. **خطة تحسين مقترحة** لكل نقطة ضعف (خطوات عملية)\n4. **توصيات للأستاذ** لتحسين تصميم الدرس\n\nاكتب الإجابة بالعربية واستخدم HTML بسيط (h4, p, ul, li, strong) للتنسيق.";

    $prompt = implode('', $promptParts);

    // استدعاء Claude
    $result = callAIProvider($prompt, $apiKey, $provider);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===================== بيانات الصفحة =====================
$questions = $db->prepare("SELECT * FROM questions WHERE lesson_id=? ORDER BY sort_order, id");
$questions->execute([$lessonId]);
$questions = $questions->fetchAll();

$gameStats = $db->prepare(
    "SELECT COUNT(*) total, SUM(completed) wins, AVG(points_earned) avg_pts, AVG(attempts) avg_att
     FROM student_games WHERE lesson_id=?"
);
$gameStats->execute([$lessonId]);
$gameStats = $gameStats->fetch();

$studentPerf = $db->prepare(
    "SELECT s.id, s.name, COUNT(sg.id) plays, SUM(sg.completed) wins,
            AVG(sg.points_earned) avg_pts, MAX(sg.played_at) last_play
     FROM students s
     JOIN student_games sg ON sg.student_id=s.id
     WHERE sg.lesson_id=?
     GROUP BY s.id ORDER BY wins DESC, avg_pts DESC"
);
$studentPerf->execute([$lessonId]);
$studentPerf = $studentPerf->fetchAll();

// إحصائيات السؤال التفصيلية
$hasQA = false;
try { $db->query("SELECT 1 FROM question_answers LIMIT 1"); $hasQA = true; } catch (Exception $e) {}

$qaStats = [];
if ($hasQA) {
    $qaRows = $db->prepare(
        "SELECT qa.question_id, COUNT(*) total, SUM(qa.is_correct) correct_count,
                q.question_text, q.correct_option
         FROM question_answers qa
         JOIN questions q ON q.id=qa.question_id
         WHERE qa.lesson_id=?
         GROUP BY qa.question_id ORDER BY (SUM(qa.is_correct)/COUNT(*)) ASC"
    );
    $qaRows->execute([$lessonId]);
    $qaStats = $qaRows->fetchAll();
}

// مفاتيح API
$claudeKey  = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';
$geminiKey  = defined('GEMINI_API_KEY')    ? GEMINI_API_KEY    : '';
$openaiKey  = defined('OPENAI_API_KEY')    ? OPENAI_API_KEY    : '';
$hasAIKey   = !empty($claudeKey) || !empty($geminiKey) || !empty($openaiKey);
$defaultProvider = !empty($claudeKey) ? 'claude' : (!empty($geminiKey) ? 'gemini' : 'openai');

// ===================== دالة استدعاء الذكاء =====================
function callAIProvider(string $prompt, string $apiKey, string $provider): array {
    if ($provider === 'claude') {
        $url = 'https://api.anthropic.com/v1/messages';
        $payload = json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 2048,
            'messages'   => [['role' => 'user', 'content' => $prompt]]
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT        => 60
        ]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) {
            $err = json_decode($resp, true); return ['error' => $err['error']['message'] ?? "خطأ HTTP $code"];
        }
        $data = json_decode($resp, true);
        $text = ''; foreach ($data['content'] ?? [] as $b) { if ($b['type'] === 'text') $text .= $b['text']; }
        return empty($text) ? ['error' => 'Claude لم يعد نصاً'] : ['html' => $text];
    }

    if ($provider === 'gemini') {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . urlencode($apiKey);
        $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['maxOutputTokens' => 2048]], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 60]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) { $err = json_decode($resp, true); return ['error' => $err['error']['message'] ?? "خطأ HTTP $code"]; }
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return empty($text) ? ['error' => 'Gemini لم يعد نصاً'] : ['html' => $text];
    }

    if ($provider === 'openai') {
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = json_encode(['model' => 'gpt-4o-mini', 'max_tokens' => 2048, 'messages' => [['role' => 'user', 'content' => $prompt]]], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey], CURLOPT_TIMEOUT => 60]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) { $err = json_decode($resp, true); return ['error' => $err['error']['message'] ?? "خطأ HTTP $code"]; }
        $data = json_decode($resp, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        return empty($text) ? ['error' => 'OpenAI لم يعد نصاً'] : ['html' => $text];
    }

    return ['error' => 'مزوّد غير معروف'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>تحليل الأسئلة – <?= clean($lesson['name']) ?></title>
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
  <a href="/teacher/lessons.php"   class="sidebar-link active"><i class="fas fa-layer-group"></i> الدروس</a>
  <a href="/teacher/scholars.php"  class="sidebar-link"><i class="fas fa-scroll"></i> قائمة العلماء</a>
  <a href="/teacher/analytics.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> التحليلات</a>
</aside>
<main class="main-content">
  <div style="margin-bottom:.75rem;font-size:.88rem;color:var(--muted);">
    <a href="/teacher/lessons.php">الدروس</a> / <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>"><?= clean($lesson['name']) ?></a> / <strong>تحليل الأسئلة</strong>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 style="font-size:1.4rem;"><i class="fas fa-chart-line" style="color:var(--accent);"></i> تحليل الأسئلة: <?= clean($lesson['name']) ?></h2>
    <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> الأسئلة</a>
  </div>

  <!-- إحصائيات عامة -->
  <div class="stats-grid" style="margin-bottom:1.5rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-question-circle" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= count($questions) ?></div>
      <div class="stat-label">عدد الأسئلة</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-gamepad" style="color:var(--accent);"></i></div>
      <div class="stat-value"><?= $gameStats['total'] ?: 0 ?></div>
      <div class="stat-label">إجمالي المحاولات</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-trophy" style="color:var(--info);"></i></div>
      <div class="stat-value"><?= $gameStats['total'] ? round(($gameStats['wins'] / $gameStats['total']) * 100) : 0 ?>%</div>
      <div class="stat-label">نسبة الفوز</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-redo" style="color:#C2185B;"></i></div>
      <div class="stat-value"><?= $gameStats['total'] ? number_format($gameStats['avg_att'], 1) : '-' ?></div>
      <div class="stat-label">متوسط المحاولات</div>
    </div>
  </div>

  <!-- تحليل الأسئلة التفصيلي -->
  <?php if ($hasQA && !empty($qaStats)): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> أداء الطلاب لكل سؤال</div></div>
    <canvas id="qaChart" height="120"></canvas>
  </div>

  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header" style="border-bottom-color:var(--danger);">
      <div class="card-title" style="color:var(--danger);font-size:1rem;"><i class="fas fa-exclamation-triangle"></i> الأسئلة الأصعب على الطلاب</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>نص السؤال</th><th>الإجابات</th><th>نسبة الصحيحة</th></tr></thead>
        <tbody>
          <?php foreach ($qaStats as $i => $qs): ?>
          <?php $rate = $qs['total'] ? round($qs['correct_count'] / $qs['total'] * 100) : 0; ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td style="max-width:350px;"><?= clean(mb_substr($qs['question_text'], 0, 80)) ?><?= mb_strlen($qs['question_text']) > 80 ? '…' : '' ?></td>
            <td><?= $qs['total'] ?></td>
            <td>
              <span style="font-weight:700;color:<?= $rate < 40 ? 'var(--danger)' : ($rate < 70 ? 'var(--accent)' : 'var(--primary)') ?>;"><?= $rate ?>%</span>
              <div style="background:#eee;border-radius:4px;height:6px;margin-top:3px;width:80px;display:inline-block;vertical-align:middle;">
                <div style="background:<?= $rate < 40 ? 'var(--danger)' : ($rate < 70 ? 'var(--accent)' : 'var(--primary)') ?>;width:<?= $rate ?>%;height:100%;border-radius:4px;"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="alert" style="background:#FFF8E1;border-right:4px solid var(--accent-lt);color:#795548;margin-bottom:1.5rem;">
    <i class="fas fa-info-circle" style="color:var(--accent);"></i>
    <strong>ملاحظة:</strong> لا تتوفر بيانات تفصيلية لكل سؤال بعد. يتم جمعها تلقائياً مع تقدم الطلاب في الدرس.
    لتفعيل التتبع التفصيلي يرجى تطبيق ملف <code>db/migration_enhanced_game_simple.sql</code> على قاعدة البيانات.
  </div>
  <?php endif; ?>

  <!-- أداء الطلاب -->
  <?php if (!empty($studentPerf)): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-users"></i> أداء الطلاب في هذا الدرس</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>الطالب</th><th>المحاولات</th><th>الفوز</th><th>متوسط النقاط</th><th>آخر محاولة</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($studentPerf as $sp): ?>
          <tr>
            <td><strong><?= clean($sp['name']) ?></strong></td>
            <td><?= $sp['plays'] ?></td>
            <td><?= $sp['wins'] ? '<span class="badge badge-primary">'. $sp['wins'] .' ✅</span>' : '<span class="badge badge-danger">0</span>' ?></td>
            <td><?= number_format($sp['avg_pts'], 1) ?></td>
            <td style="font-size:.82rem;"><?= date('d/m/Y', strtotime($sp['last_play'])) ?></td>
            <td><a href="/teacher/analytics.php?student_id=<?= $sp['id'] ?>" class="btn btn-info btn-sm" title="تحليل كامل"><i class="fas fa-chart-line"></i></a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- زر تحليل الذكاء الاصطناعي -->
  <div class="card" style="border-right:4px solid var(--dark);">
    <div class="card-header" style="border-bottom-color:var(--dark);">
      <div class="card-title" style="color:var(--dark);font-size:1rem;"><i class="fas fa-robot"></i> تحليل بالذكاء الاصطناعي (Claude / Gemini)</div>
    </div>
    <p style="color:var(--muted);font-size:.9rem;margin-bottom:1rem;">يحلّل الذكاء الاصطناعي نتائج الطلاب ويُقدّم خطة تحسين مخصصة.</p>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
      <select id="aiProvider" class="form-control" style="width:auto;">
        <option value="claude" <?= !empty($claudeKey) ? 'selected' : '' ?>>Claude Haiku <?= !empty($claudeKey) ? '✓' : '' ?></option>
        <option value="gemini" <?= empty($claudeKey) && !empty($geminiKey) ? 'selected' : '' ?>>Google Gemini <?= !empty($geminiKey) ? '✓' : '' ?></option>
        <option value="openai">OpenAI GPT-4o-mini <?= !empty($openaiKey) ? '✓' : '' ?></option>
      </select>
      <input type="password" id="aiApiKey" class="form-control" placeholder="مفتاح API (اختياري إذا كان محفوظاً)" style="max-width:360px;" autocomplete="new-password">
      <button id="aiAnalyzeBtn" class="btn btn-accent" onclick="runAIAnalysis()">
        <i class="fas fa-robot"></i> تحليل بالذكاء الاصطناعي
      </button>
    </div>

    <div id="aiResult" style="display:none;margin-top:1rem;padding:1.25rem;background:#F9FBE7;border-radius:var(--radius-sm);border:1px solid #DCE775;"></div>
    <div id="aiError"  style="display:none;margin-top:.75rem;" class="alert alert-danger"></div>
  </div>
</main>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

<?php if ($hasQA && !empty($qaStats)): ?>
// رسم مخطط الأسئلة
const qaLabels  = <?= json_encode(array_map(fn($q) => mb_substr($q['question_text'], 0, 20) . '…', $qaStats), JSON_UNESCAPED_UNICODE) ?>;
const qaRates   = <?= json_encode(array_map(fn($q) => $q['total'] ? round($q['correct_count'] / $q['total'] * 100) : 0, $qaStats)) ?>;
const qaColors  = qaRates.map(r => r < 40 ? '#D32F2F' : (r < 70 ? '#FF8F00' : '#2E7D32'));
new Chart(document.getElementById('qaChart'), {
  type: 'bar',
  data: {
    labels: qaLabels,
    datasets: [{ label: 'نسبة الإجابة الصحيحة %', data: qaRates, backgroundColor: qaColors }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } }, x: { ticks: { font: { family: 'Tajawal' } } } }
  }
});
<?php endif; ?>

async function runAIAnalysis() {
  const btn = document.getElementById('aiAnalyzeBtn');
  const result = document.getElementById('aiResult');
  const errBox = document.getElementById('aiError');
  const provider = document.getElementById('aiProvider').value;
  const apiKey   = document.getElementById('aiApiKey').value.trim();

  btn.disabled = true;
  btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:3px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-left:.4rem;vertical-align:middle;"></span> جارٍ التحليل…';
  result.style.display = 'none';
  errBox.style.display  = 'none';

  try {
    const fd = new FormData();
    fd.append('action',   'ai_analyze');
    fd.append('provider', provider);
    fd.append('api_key',  apiKey);

    const resp = await fetch('?lesson_id=<?= $lessonId ?>', { method: 'POST', body: fd });
    const data = await resp.json();

    if (data.error) {
      errBox.innerHTML = '<i class="fas fa-times-circle"></i> ' + data.error;
      errBox.style.display = 'block';
    } else {
      result.innerHTML = data.html;
      result.style.display = 'block';
    }
  } catch (e) {
    errBox.innerHTML = '<i class="fas fa-times-circle"></i> خطأ في الاتصال: ' + e.message;
    errBox.style.display = 'block';
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-robot"></i> تحليل بالذكاء الاصطناعي';
}
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
</body>
</html>

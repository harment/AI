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

// Load API keys from .env via constants defined in config/db.php
$envKeys = [
    'gemini' => defined('GEMINI_API_KEY')    ? GEMINI_API_KEY    : '',
    'openai' => defined('OPENAI_API_KEY')    ? OPENAI_API_KEY    : '',
    'claude' => defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '',
];
$configuredProviders = array_keys(array_filter($envKeys, fn($v) => $v !== ''));

$msg = ''; $msgType = 'success';
$analysis = null;

// Fetch questions for this lesson
$questions = $db->prepare("SELECT * FROM questions WHERE lesson_id=? ORDER BY sort_order, id");
$questions->execute([$lessonId]);
$questions = $questions->fetchAll();

// ---------- POST: Run AI Analysis ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider     = $_POST['provider'] ?? 'gemini';
    $submittedKey = trim($_POST['api_key_override'] ?? '');

    // Pick the correct key for the selected provider
    $apiKey = $submittedKey ?: ($envKeys[$provider] ?? '');

    if (empty($apiKey)) {
        $msg     = 'لم يتم تعيين مفتاح API للمزود المختار. يرجى إدخاله أو إضافته في ملف .env.';
        $msgType = 'danger';
    } elseif (empty($questions)) {
        $msg     = 'لا توجد أسئلة لهذا الدرس. يرجى توليد الأسئلة أولاً.';
        $msgType = 'warning';
    } else {
        // Build question list for prompt
        $questionList = '';
        foreach ($questions as $i => $q) {
            $correctLabels = ['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'];
            $correctLabel  = $correctLabels[$q['correct_option']] ?? $q['correct_option'];
            $questionList .= ($i + 1) . ". " . $q['question_text'] . "\n";
            $questionList .= "   أ) " . $q['option_a'] . "\n";
            $questionList .= "   ب) " . $q['option_b'] . "\n";
            $questionList .= "   ج) " . $q['option_c'] . "\n";
            $questionList .= "   د) " . $q['option_d'] . "\n";
            $questionList .= "   الإجابة الصحيحة: {$correctLabel}\n\n";
        }

        $totalQ = count($questions);
        $prompt = "أنت خبير في تقييم أسئلة الاختبارات التربوية. فيما يلي قائمة بـ {$totalQ} سؤال اختيار من متعدد لدرس «{$lesson['name']}»:\n\n{$questionList}\n\nقدّم تحليلاً شاملاً لهذه الأسئلة يشمل:\n1. تقييم جودة الأسئلة وصياغتها من 10\n2. توزيع مستويات الصعوبة (سهل / متوسط / صعب)\n3. تغطية المحتوى ومدى التنوع\n4. نقاط القوة في الأسئلة\n5. نقاط الضعف والأسئلة التي تحتاج تحسيناً\n6. توصيات محددة لتحسين الأسئلة\n\nأجب بـ JSON بالهيكل التالي:\n{\"quality_score\":8,\"difficulty_distribution\":{\"easy\":5,\"medium\":15,\"hard\":10},\"content_coverage\":\"تحليل التغطية...\",\"strengths\":[\"نقطة قوة 1\",\"نقطة قوة 2\"],\"weaknesses\":[\"نقطة ضعف 1\",\"نقطة ضعف 2\"],\"recommendations\":[\"توصية 1\",\"توصية 2\"],\"question_notes\":[{\"number\":1,\"note\":\"ملاحظة\",\"status\":\"good\"}]}";

        $result = callAnalysisAI($prompt, $apiKey, $provider);

        if ($result['success']) {
            $cleanText = preg_replace('/^```(?:json)?\s*/i', '', trim($result['text']));
            $cleanText = preg_replace('/```\s*$/', '', $cleanText);
            $parsed    = @json_decode(trim($cleanText), true);

            if (is_array($parsed) && json_last_error() === JSON_ERROR_NONE) {
                $analysis = $parsed;
                $msg      = 'تم تحليل الأسئلة بنجاح!';
            } else {
                $msg     = 'تم الاستجابة من AI لكن تعذّر تحليل النتيجة. تأكد من المزود أو أعد المحاولة.';
                $msgType = 'warning';
                // Show raw text for debugging
                $analysis = ['raw' => $result['text']];
            }
        } else {
            $msg     = 'فشل الاستدعاء: ' . $result['error'];
            $msgType = 'danger';
        }
    }
}

// ---- AI call helper (uses correct key per provider) ----
function callAnalysisAI(string $prompt, string $apiKey, string $provider): array
{
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'لم يتم تعيين مفتاح API'];
    }

    // Gemini
    if ($provider === 'gemini') {
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . urlencode($apiKey);
        $payload = json_encode([
            'contents'        => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['maxOutputTokens' => 4096, 'temperature' => 0.2],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 90,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $data   = @json_decode($resp, true);
            $apiErr = $data['error']['message'] ?? $resp;
            return ['success' => false, 'error' => "خطأ من Gemini (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        $data = @json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return empty($text)
            ? ['success' => false, 'error' => 'Gemini لم يعيد أي نص']
            : ['success' => true, 'text' => trim($text)];
    }

    // OpenAI
    if ($provider === 'openai') {
        $url     = "https://api.openai.com/v1/chat/completions";
        $payload = json_encode([
            'model'       => 'gpt-4o-mini',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => 4096,
            'temperature' => 0.2,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT        => 90,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $data   = @json_decode($resp, true);
            $apiErr = $data['error']['message'] ?? $resp;
            return ['success' => false, 'error' => "خطأ من OpenAI (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        $data = @json_decode($resp, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        return empty($text)
            ? ['success' => false, 'error' => 'OpenAI لم يعيد أي نص']
            : ['success' => true, 'text' => trim($text)];
    }

    // Claude (Anthropic)
    if ($provider === 'claude') {
        $url     = "https://api.anthropic.com/v1/messages";
        $payload = json_encode([
            'model'       => 'claude-haiku-4-5-20251001',
            'max_tokens'  => 4096,
            'temperature' => 0.2,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 90,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $data   = @json_decode($resp, true);
            $apiErr = $data['error']['message'] ?? $resp;
            return ['success' => false, 'error' => "خطأ من Claude (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        $data = @json_decode($resp, true);
        $text = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text') $text .= $block['text'];
            }
        }
        return empty($text)
            ? ['success' => false, 'error' => 'Claude لم يعيد أي نص']
            : ['success' => true, 'text' => trim($text)];
    }

    return ['success' => false, 'error' => 'مزود غير معروف'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>تحليل الأسئلة – <?= clean($lesson['name']) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
    <a href="/teacher/lessons.php">الدروس</a> /
    <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>"><?= clean($lesson['name']) ?></a> /
    <strong>تحليل الأسئلة بالذكاء</strong>
  </div>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2><i class="fas fa-brain"></i> تحليل أسئلة: <?= clean($lesson['name']) ?></h2>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
      <a href="/teacher/ai_generate.php?lesson_id=<?= $lessonId ?>" class="btn btn-accent btn-sm"><i class="fas fa-robot"></i> العودة للذكاء الاصطناعي</a>
      <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> الأسئلة</a>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= clean($msg) ?></div>
  <?php endif; ?>

  <?php if (empty($questions)): ?>
  <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> لا توجد أسئلة لهذا الدرس. <a href="/teacher/ai_generate.php?lesson_id=<?= $lessonId ?>">توليد أسئلة بالذكاء الاصطناعي</a></div>
  <?php else: ?>

  <!-- Settings Card -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-cog"></i> إعدادات التحليل</div></div>
    <form method="POST">
      <div style="display:grid;grid-template-columns:auto 1fr;gap:1rem;align-items:center;padding:1rem;">
        <label class="form-label" style="margin:0;">المزوّد</label>
        <select name="provider" id="providerSel" class="form-control" style="width:auto;max-width:400px;" onchange="updateKeyStatus()">
          <option value="gemini" <?= in_array('gemini', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            Google Gemini <?= in_array('gemini', $configuredProviders) ? '✓' : '' ?>
          </option>
          <option value="openai" <?= in_array('openai', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            OpenAI GPT-4o-mini <?= in_array('openai', $configuredProviders) ? '✓' : '' ?>
          </option>
          <option value="claude" <?= in_array('claude', $configuredProviders) ? 'data-has-key="1"' : '' ?>>
            Anthropic Claude <?= in_array('claude', $configuredProviders) ? '✓' : '' ?>
          </option>
        </select>

        <label class="form-label" style="margin:0;">مفتاح API (اختياري)</label>
        <div>
          <input type="password" name="api_key_override" id="apiKeyOverride" class="form-control"
                 placeholder="اتركه فارغاً لاستخدام المفتاح المحفوظ في .env"
                 autocomplete="new-password" style="max-width:480px;">
          <small id="keyStatus" style="display:block;margin-top:.35rem;color:var(--muted);"></small>
        </div>
      </div>
      <div style="padding:0 1rem 1rem;">
        <div style="margin-bottom:.75rem;">
          <span class="badge badge-primary"><?= count($questions) ?> سؤال سيتم تحليله</span>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-brain"></i> تشغيل تحليل الأسئلة
        </button>
      </div>
    </form>
  </div>

  <?php if ($analysis): ?>

  <?php if (isset($analysis['raw'])): ?>
  <!-- Raw output fallback -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-file-alt"></i> نتيجة التحليل (نص خام)</div></div>
    <div style="padding:1rem;white-space:pre-wrap;font-family:monospace;font-size:.85rem;background:#f5f5f5;border-radius:4px;"><?= clean($analysis['raw']) ?></div>
  </div>
  <?php else: ?>

  <!-- Quality Score -->
  <?php $score = (int)($analysis['quality_score'] ?? 0); ?>
  <div class="stats-grid" style="margin-bottom:1.5rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background:#E8F5E9;"><i class="fas fa-star" style="color:var(--primary);"></i></div>
      <div class="stat-value"><?= $score ?>/10</div>
      <div class="stat-label">تقييم الجودة</div>
    </div>
    <?php $diff = $analysis['difficulty_distribution'] ?? []; ?>
    <div class="stat-card">
      <div class="stat-icon" style="background:#E3F2FD;"><i class="fas fa-signal" style="color:var(--info);"></i></div>
      <div class="stat-value"><?= (int)($diff['easy'] ?? 0) ?></div>
      <div class="stat-label">سؤال سهل</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFF3E0;"><i class="fas fa-signal" style="color:var(--accent);"></i></div>
      <div class="stat-value"><?= (int)($diff['medium'] ?? 0) ?></div>
      <div class="stat-label">سؤال متوسط</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FCE4EC;"><i class="fas fa-signal" style="color:var(--danger);"></i></div>
      <div class="stat-value"><?= (int)($diff['hard'] ?? 0) ?></div>
      <div class="stat-label">سؤال صعب</div>
    </div>
  </div>

  <!-- Content Coverage -->
  <?php if (!empty($analysis['content_coverage'])): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-book-open"></i> تغطية المحتوى</div></div>
    <div style="padding:1rem;"><?= clean($analysis['content_coverage']) ?></div>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
    <!-- Strengths -->
    <?php if (!empty($analysis['strengths'])): ?>
    <div class="card">
      <div class="card-header"><div class="card-title" style="color:#2E7D32;"><i class="fas fa-thumbs-up"></i> نقاط القوة</div></div>
      <ul style="padding:1rem 1.5rem;margin:0;">
        <?php foreach ((array)$analysis['strengths'] as $s): ?>
        <li style="margin-bottom:.5rem;"><?= clean($s) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Weaknesses -->
    <?php if (!empty($analysis['weaknesses'])): ?>
    <div class="card">
      <div class="card-header"><div class="card-title" style="color:var(--danger);"><i class="fas fa-thumbs-down"></i> نقاط الضعف</div></div>
      <ul style="padding:1rem 1.5rem;margin:0;">
        <?php foreach ((array)$analysis['weaknesses'] as $w): ?>
        <li style="margin-bottom:.5rem;"><?= clean($w) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recommendations -->
  <?php if (!empty($analysis['recommendations'])): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-lightbulb"></i> التوصيات</div></div>
    <ol style="padding:1rem 2rem;margin:0;">
      <?php foreach ((array)$analysis['recommendations'] as $r): ?>
      <li style="margin-bottom:.5rem;"><?= clean($r) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endif; ?>

  <!-- Per-question notes -->
  <?php if (!empty($analysis['question_notes'])): ?>
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-clipboard-list"></i> ملاحظات على الأسئلة</div></div>
    <div class="table-wrap"><table>
      <thead><tr><th>رقم السؤال</th><th>الحالة</th><th>الملاحظة</th></tr></thead>
      <tbody>
        <?php foreach ((array)$analysis['question_notes'] as $note): ?>
        <?php $status = $note['status'] ?? 'neutral'; ?>
        <tr>
          <td><strong><?= clean((string)($note['number'] ?? '')) ?></strong></td>
          <td>
            <?php if ($status === 'good'): ?>
            <span class="badge badge-primary">✅ جيد</span>
            <?php elseif ($status === 'needs_improvement'): ?>
            <span class="badge badge-danger">⚠️ يحتاج تحسين</span>
            <?php else: ?>
            <span class="badge" style="background:var(--border);color:var(--text);">محايد</span>
            <?php endif; ?>
          </td>
          <td><?= clean($note['note'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <?php endif; // end raw check ?>
  <?php endif; // end $analysis check ?>

  <?php endif; // end empty questions check ?>

  <div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;">
    <a href="/teacher/ai_generate.php?lesson_id=<?= $lessonId ?>" class="btn btn-accent">
      <i class="fas fa-robot"></i> العودة للذكاء الاصطناعي
    </a>
    <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>" class="btn btn-outline">
      <i class="fas fa-arrow-right"></i> الأسئلة
    </a>
  </div>
</main>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

const CONFIGURED = <?= json_encode($configuredProviders, JSON_UNESCAPED_UNICODE) ?>;

function updateKeyStatus() {
    const provider = document.getElementById('providerSel').value;
    const statusEl = document.getElementById('keyStatus');
    if (CONFIGURED.includes(provider)) {
        statusEl.textContent = '✅ مفتاح محفوظ في .env';
        statusEl.style.color = 'var(--primary)';
    } else {
        statusEl.textContent = '⚠️ لا يوجد مفتاح محفوظ – يرجى إدخاله';
        statusEl.style.color = 'var(--danger)';
    }
}

document.addEventListener('DOMContentLoaded', updateKeyStatus);

document.querySelector('form').addEventListener('submit', function() {
    const btn = this.querySelector('[type="submit"]');
    if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;margin-left:.4rem;"></span> جارٍ التحليل…';
        setTimeout(() => { btn.disabled = false; }, 120000);
    }
});
</script>
</body>
</html>

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

$msg = ''; $msgType = 'success';
$generate = $_GET['generate'] ?? '';

// After form submission: AI generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $genType  = $_POST['gen_type'] ?? '';
    $apiKey   = trim($_POST['api_key'] ?? GEMINI_API_KEY);
    $provider = $_POST['provider'] ?? 'gemini';

    if ($genType === 'questions') {
        // Generate 7 questions using AI
        $prompt = "أنت أستاذ لغة عربية متخصص في النحو. الدرس: «{$lesson['name']}». اصنع 7 أسئلة اختيار من متعدد باللغة العربية الفصحى لاختبار الطلاب غير الناطقين بالعربية. كل سؤال له 4 خيارات (أ، ب، ج، د) وإجابة صحيحة واحدة وتغذية راجعة. أجب بصيغة JSON array هكذا:\n[{\"question_text\":\"...\",\"option_a\":\"...\",\"option_b\":\"...\",\"option_c\":\"...\",\"option_d\":\"...\",\"correct_option\":\"a\",\"feedback_correct\":\"...\",\"feedback_wrong\":\"...\"}]";
        $result = callAI($prompt, $apiKey, $provider);
        if ($result['success']) {
            $parsed = @json_decode($result['text'], true);
            if (is_array($parsed)) {
                foreach ($parsed as $q) {
                    if (!empty($q['question_text'])) {
                        $db->prepare("INSERT INTO questions (lesson_id,question_text,option_a,option_b,option_c,option_d,correct_option,feedback_correct,feedback_wrong) VALUES (?,?,?,?,?,?,?,?,?)")
                           ->execute([$lessonId, $q['question_text'], $q['option_a']??'', $q['option_b']??'', $q['option_c']??'', $q['option_d']??'', $q['correct_option']??'a', $q['feedback_correct']??'', $q['feedback_wrong']??'']);
                    }
                }
                $msg = 'تم توليد ' . count($parsed) . ' سؤال بنجاح!';
            } else {
                $msg = 'تم الاستجابة من الذكاء لكن التنسيق لم يكن JSON. النص: ' . mb_substr($result['text'], 0, 500);
                $msgType = 'warning';
            }
        } else {
            $msg = 'فشل الاستدعاء: ' . $result['error'];
            $msgType = 'danger';
        }
    }

    if ($genType === 'presentation') {
        $contentSummary = trim($_POST['content_summary'] ?? '');
        $prompt = "أنت مصمم عروض تقديمية تعليمية. الدرس: «{$lesson['name']}». الملخص: {$contentSummary}\nاصنع عرضاً تقديمياً HTML جميلاً وجذاباً باللغة العربية يتضمن 5-7 شرائح مع عناوين وعناصر مرئية ملونة ونقاط رئيسية. استخدم HTML+CSS فقط داخل <div class='slides'>...</div>.";
        $result = callAI($prompt, $apiKey, $provider);
        if ($result['success']) {
            $db->prepare("UPDATE lessons SET presentation_html=? WHERE id=?")->execute([$result['text'], $lessonId]);
            $msg = 'تم توليد العرض التقديمي بنجاح!';
        } else {
            $msg = 'فشل توليد العرض: ' . $result['error']; $msgType = 'danger';
        }
    }

    if ($genType === 'scholar') {
        $scholarName = trim($_POST['scholar_name'] ?? '');
        if ($scholarName) {
            $prompt = "اكتب معلومات موجزة عن عالم النحو العربي «{$scholarName}» باللغة العربية الفصحى. أجب بصيغة JSON: {\"name\":\"...\",\"era\":\"...\",\"short_bio\":\"...\",\"works\":\"...\"}";
            $result = callAI($prompt, $apiKey, $provider);
            if ($result['success']) {
                $parsed = @json_decode($result['text'], true);
                if ($parsed) {
                    $db->prepare("INSERT INTO scholars (name, era, short_bio, works) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE era=VALUES(era), short_bio=VALUES(short_bio), works=VALUES(works)")
                       ->execute([$parsed['name'] ?? $scholarName, $parsed['era'] ?? '', $parsed['short_bio'] ?? '', $parsed['works'] ?? '']);
                    $msg = 'تمت إضافة/تحديث العالم: ' . clean($scholarName);
                }
            } else {
                $msg = 'فشل الاستدعاء: ' . $result['error']; $msgType = 'danger';
            }
        }
    }
}

/**
 * استدعاء Gemini أو OpenAI عبر cURL
 */
function callAI(string $prompt, string $apiKey, string $provider): array {
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'لم يتم تعيين مفتاح API. يرجى إدخاله في الحقل أدناه أو في ملف .env (GEMINI_API_KEY=...).'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'مكتبة cURL غير مفعّلة على الخادم. يرجى تفعيل php-curl.'];
    }

    if ($provider === 'gemini') {
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-04-17:generateContent?key=" . urlencode($apiKey);
        $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $curlErr) {
            return ['success' => false, 'error' => 'فشل الاتصال بـ Gemini: ' . $curlErr];
        }
        $data = json_decode($resp, true);
        if ($httpCode !== 200) {
            $apiErr = $data['error']['message'] ?? $resp;
            return ['success' => false, 'error' => "خطأ من Gemini (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        // Strip markdown code fences
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        return ['success' => true, 'text' => trim($text)];
    }

    if ($provider === 'openai') {
        $url     = "https://api.openai.com/v1/chat/completions";
        $payload = json_encode(['model' => 'gpt-4o-mini', 'messages' => [['role' => 'user', 'content' => $prompt]], 'max_tokens' => 2000]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $curlErr) {
            return ['success' => false, 'error' => 'فشل الاتصال بـ OpenAI: ' . $curlErr];
        }
        $data = json_decode($resp, true);
        if ($httpCode !== 200) {
            $apiErr = $data['error']['message'] ?? $resp;
            return ['success' => false, 'error' => "خطأ من OpenAI (HTTP $httpCode): " . mb_substr($apiErr, 0, 300)];
        }
        $text = $data['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'text' => trim($text)];
    }

    return ['success' => false, 'error' => 'مزود غير معروف'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>الذكاء الاصطناعي – <?= clean($lesson['name']) ?></title>
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
    <a href="/teacher/lessons.php">الدروس</a> / <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>"><?= clean($lesson['name']) ?></a> / <strong>الذكاء الاصطناعي</strong>
  </div>
  <h2 style="margin-bottom:1.5rem;"><i class="fas fa-robot" style="color:var(--accent);"></i> توليد المحتوى بالذكاء الاصطناعي</h2>
  <p style="color:var(--muted);margin-bottom:1.5rem;">الدرس: <strong><?= clean($lesson['name']) ?></strong> – <?= clean($lesson['course_name']) ?></p>

  <?php if ($msg): ?>
  <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= clean($msg) ?></div>
  <?php endif; ?>

  <!-- API Key & Provider -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><div class="card-title"><i class="fas fa-key"></i> إعدادات الذكاء الاصطناعي</div></div>
    <div style="display:grid;grid-template-columns:auto 1fr;gap:1rem;align-items:center;">
      <label class="form-label" style="margin:0;">المزوّد</label>
      <select id="providerSel" class="form-control" style="width:auto;">
        <option value="gemini">Google Gemini (مجاني)</option>
        <option value="openai">OpenAI GPT</option>
      </select>
      <label class="form-label" style="margin:0;">مفتاح API</label>
      <input type="text" id="apiKeyInput" class="form-control" value="<?= clean(GEMINI_API_KEY) ?>" placeholder="أدخل مفتاح الـ API هنا…" autocomplete="off">
    </div>
  </div>

  <!-- Generation cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">

    <!-- Questions -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-question-circle"></i> توليد الأسئلة</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">يولد 7 أسئلة اختيار من متعدد من الدرس مع تغذية راجعة تعليمية.</p>
      <form method="POST">
        <input type="hidden" name="gen_type" value="questions">
        <input type="hidden" name="provider" id="qProvider" value="gemini">
        <input type="hidden" name="api_key"  id="qApiKey"   value="">
        <button type="submit" class="btn btn-primary btn-block" onclick="setApiVars(this.form)">
          <i class="fas fa-magic"></i> توليد الأسئلة
        </button>
      </form>
    </div>

    <!-- Presentation -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-file-powerpoint" style="color:#E53935;"></i> توليد العرض التقديمي</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">يولد عرضاً HTML تفاعلياً وجذاباً من ملخص الدرس.</p>
      <form method="POST">
        <input type="hidden" name="gen_type" value="presentation">
        <input type="hidden" name="provider" id="pProvider" value="gemini">
        <input type="hidden" name="api_key"  id="pApiKey"   value="">
        <div class="form-group"><label class="form-label">ملخص المحتوى</label>
          <textarea name="content_summary" class="form-control" rows="3" placeholder="الصق هنا محتوى الدرس أو ملخصاً منه…"></textarea>
        </div>
        <button type="submit" class="btn btn-danger btn-block" onclick="setApiVars(this.form,'p')">
          <i class="fas fa-magic"></i> توليد العرض
        </button>
      </form>
    </div>

    <!-- Scholar -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-scroll"></i> إضافة عالم نحو بالذكاء</div></div>
      <p style="font-size:.88rem;color:var(--muted);margin-bottom:1rem;">اكتب اسم العالم وسيجلب الذكاء معلومات موجزة عنه.</p>
      <form method="POST">
        <input type="hidden" name="gen_type" value="scholar">
        <input type="hidden" name="provider" id="sProvider" value="gemini">
        <input type="hidden" name="api_key"  id="sApiKey"   value="">
        <div class="form-group"><label class="form-label">اسم العالم</label>
          <input type="text" name="scholar_name" class="form-control" placeholder="مثال: سيبويه، ابن مالك…">
        </div>
        <button type="submit" class="btn btn-accent btn-block" onclick="setApiVars(this.form,'s')">
          <i class="fas fa-magic"></i> جلب المعلومات
        </button>
      </form>
    </div>

  </div>

  <div style="margin-top:1.5rem;text-align:center;">
    <a href="/teacher/questions.php?lesson_id=<?= $lessonId ?>" class="btn btn-outline">
      <i class="fas fa-arrow-right"></i> عودة إلى أسئلة الدرس
    </a>
  </div>
</main>

<script src="/assets/js/app.js"></script>
<script>
if (window.innerWidth < 900) document.getElementById('sidebarToggle').style.display = 'block';

function setApiVars(form, prefix) {
  const key      = document.getElementById('apiKeyInput').value;
  const provider = document.getElementById('providerSel').value;
  const pField   = form.querySelector('[name="provider"]');
  const kField   = form.querySelector('[name="api_key"]');
  if (pField) pField.value = provider;
  if (kField) kField.value = key;
}

// Loading state on submit
document.querySelectorAll('form').forEach(f => {
  f.addEventListener('submit', function() {
    const btn = this.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-left:.4rem;"></span> جارٍ التوليد…'; }
  });
});
</script>
</body>
</html>

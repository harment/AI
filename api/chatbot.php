<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
if (function_exists('startSession')) {
    startSession();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'reply' => 'حدث خطأ داخلي في الخادم.',
        'mode' => 'text',
        'source' => 'fallback'
    ], JSON_UNESCAPED_UNICODE);
    error_log('chatbot.php exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Fallback getDB in case includes/functions.php does not define it
 */
if (!function_exists('getDB')) {
    function getDB(): PDO {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $name = defined('DB_NAME') ? DB_NAME : 'AI';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

/**
 * /api/chatbot.php
 * =========================================================
 * Smart Arabic-learning chatbot API
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['reply' => 'يرجى استخدام طريقة POST.'], 405);
}

if (!isset($_SESSION['chatbot_ctx']) || !is_array($_SESSION['chatbot_ctx'])) {
    $_SESSION['chatbot_ctx'] = [];
}
$chatCtx = &$_SESSION['chatbot_ctx'];
$chatCtx['last_topic'] = $chatCtx['last_topic'] ?? '';
$chatCtx['last_message_at'] = time();

$isMultipart = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data');

$message   = '';
$lessonId  = 0;
$audioPath = '';
$audioMime = '';

if ($isMultipart) {
    $message  = trim((string)($_POST['message'] ?? ''));
    $lessonId = (int)($_POST['lesson_id'] ?? 0);

    if (isset($_FILES['audio']) && is_array($_FILES['audio'])) {
        $err = (int)($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_OK) {
            $audioPath = (string)($_FILES['audio']['tmp_name'] ?? '');

            $clientMime = trim((string)($_POST['audio_mime'] ?? ''));
            $fallbackMime = $clientMime !== '' ? $clientMime : (string)($_FILES['audio']['type'] ?? 'audio/webm');
            $audioMime = detectMimeType($audioPath, $fallbackMime);
        }
    }
} else {
    $raw  = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    if (!is_array($json)) $json = [];

    $message  = trim((string)($json['message'] ?? ''));
    $lessonId = (int)($json['lesson_id'] ?? 0);
}

if ($message === '' && $audioPath === '') {
    out(['reply' => 'الرسالة فارغة. أرسل نصًا أو تسجيلًا صوتيًا.'], 422);
}

if ($message !== '') {
    $message = normalizeArabicQuestion($message);
}

$db = getDB();
$lessonCtx = resolveLessonContext($db, $lessonId);

if ($message !== '') {
    $topic = detectTopic($message);
    if ($topic !== null) $chatCtx['last_topic'] = $topic;

    $expanded = resolveEllipsisByContext($message, (string)$chatCtx['last_topic']);
    if ($expanded !== null) $message = $expanded;
}

if ($message !== '') {
    $policyReply = enforceGamePolicy($message, (string)($lessonCtx['name'] ?? ''));
    if ($policyReply !== null) {
        out([
            'reply'  => $policyReply,
            'mode'   => ($audioPath !== '' ? 'audio' : 'text'),
            'source' => 'policy'
        ]);
    }
}

if ($message !== '') {
    if (isPlatformQuestion($message) || ((string)$chatCtx['last_topic'] === 'platform_games')) {
        $platformReply = tryPlatformRulesAnswer($message);
        if ($platformReply !== null) {
            $chatCtx['last_topic'] = 'platform_games';
            out([
                'reply'  => $platformReply,
                'mode'   => ($audioPath !== '' ? 'audio' : 'text'),
                'source' => 'direct'
            ]);
        }
    }
}

if ($message !== '') {
    $directReply = tryDirectAnswer($db, $message, $lessonCtx);
    if ($directReply !== null) {
        out([
            'reply'  => $directReply,
            'mode'   => 'text',
            'source' => 'direct'
        ]);
    }
}

$apiKey = defined('GEMINI_API_KEY') ? trim((string)GEMINI_API_KEY) : '';
if ($apiKey === '') {
    $seed = $message !== '' ? $message : 'رسالة صوتية';
    $fallback = getRuleBasedReply($seed, $lessonCtx, (string)$chatCtx['last_topic']);
    out([
        'reply'  => $fallback,
        'mode'   => ($audioPath !== '' ? 'audio' : 'text'),
        'source' => 'fallback'
    ]);
}

$systemPrompt = <<<SYS
أنت مساعد أكاديمي ذكي داخل منصة تعليم العربية لغير الناطقين بها.

قواعد إلزامية:
1) أجب بالعربية الفصحى الواضحة.
2) ابدأ الإجابة مباشرة دون مقدمات ترحيبية متكررة.
3) إذا توفر نص الدرس من PDF فاعتمد عليه أولًا.
4) إذا لم توجد المعلومة في نص الدرس اكتب حرفيًا: "غير مذكور في محتوى هذا الدرس".
5) لا تقدّم الحل النهائي لأسئلة الألعاب أو الاختبارات.
6) عند طلب حل مباشر: قدّم تلميحات وخطوات تفكير ومثالًا مشابهًا فقط.
7) إذا كان السؤال عن قوانين اللعب/المغامرة/النقاط/المحاولات، فأجب مباشرة بقواعد المنصة.
SYS;

// Audio flow
if ($audioPath !== '') {
    $audioBin = @file_get_contents($audioPath);
    if ($audioBin === false || strlen($audioBin) === 0) {
        out(['reply' => 'تعذر قراءة الملف الصوتي.', 'mode' => 'audio', 'source' => 'fallback'], 422);
    }

    $lessonBlock = buildLessonBlockForPrompt($lessonCtx);

    $sttPrompt = <<<TXT
حوّل هذا التسجيل الصوتي إلى نص عربي واضح فقط.
- لا تشرح ولا تجب.
- إذا كان غير واضح اكتب: "غير واضح".
TXT;

    $sttResp = callGemini($apiKey, [[
        'parts' => [
            ['text' => $sttPrompt],
            ['inlineData' => [
                'mimeType' => $audioMime !== '' ? $audioMime : 'audio/webm',
                'data'     => base64_encode($audioBin)
            ]]
        ]
    ]]);

    $transcript = sanitizeReply(extractGeminiReply($sttResp));

    if ($transcript === '' || mb_strtolower($transcript) === 'غير واضح') {
        if ($message !== '') {
            $transcript = normalizeArabicQuestion($message);
        } else {
            out([
                'reply'  => 'الصوت غير واضح بما يكفي. تكلّم قرب الميكروفون لمدة 2-3 ثوانٍ وفي مكان هادئ.',
                'mode'   => 'audio',
                'source' => 'fallback'
            ]);
        }
    }

    $transcript = normalizeArabicQuestion($transcript);

    $topic2 = detectTopic($transcript);
    if ($topic2 !== null) $chatCtx['last_topic'] = $topic2;

    $expanded2 = resolveEllipsisByContext($transcript, (string)$chatCtx['last_topic']);
    if ($expanded2 !== null) $transcript = $expanded2;

    $policyReply2 = enforceGamePolicy($transcript, (string)($lessonCtx['name'] ?? ''));
    if ($policyReply2 !== null) {
        out(['reply' => $policyReply2, 'mode' => 'audio', 'source' => 'policy']);
    }

    if (isPlatformQuestion($transcript) || ((string)$chatCtx['last_topic'] === 'platform_games')) {
        $platformReply2 = tryPlatformRulesAnswer($transcript);
        if ($platformReply2 !== null) {
            $chatCtx['last_topic'] = 'platform_games';
            out(['reply' => $platformReply2, 'mode' => 'audio', 'source' => 'direct']);
        }
    }

    $direct2 = tryDirectAnswer($db, $transcript, $lessonCtx);
    if ($direct2 !== null) {
        out(['reply' => $direct2, 'mode' => 'audio', 'source' => 'direct']);
    }

    $answerPrompt = $systemPrompt
        . "\n\n[نص السؤال المستخرج من الصوت]\n" . $transcript
        . "\n\n" . ($lessonBlock !== '' ? $lessonBlock : "[سياق]\nلا يوجد سياق درس ممرّر.");

    $ansResp = callGemini($apiKey, [[ 'parts' => [['text' => $answerPrompt]] ]]);
    $finalReply = sanitizeReply(extractGeminiReply($ansResp));

    if (isPlatformQuestion($transcript) && ($finalReply === '' || str_contains($finalReply, 'غير مذكور في محتوى هذا الدرس'))) {
        $platformFallback = tryPlatformRulesAnswer($transcript);
        if ($platformFallback !== null) {
            out(['reply' => $platformFallback, 'mode' => 'audio', 'source' => 'direct']);
        }
    }

    if ($finalReply === '') {
        $finalReply = getRuleBasedReply($transcript, $lessonCtx, (string)$chatCtx['last_topic']);
        $src = 'fallback';
    } else {
        $src = (($lessonCtx['pdf_text'] ?? '') !== '' ? 'lesson' : 'general');
    }

    out(['reply' => $finalReply, 'mode' => 'audio', 'source' => $src]);
}

// Text flow
$lessonBlock = buildLessonBlockForPrompt($lessonCtx);

$textPrompt = $systemPrompt
    . "\n\n" . ($lessonBlock !== '' ? $lessonBlock : "[سياق]\nلا يوجد سياق درس ممرّر.")
    . "\n\n[سؤال الطالب]\n" . $message;

$textResp = callGemini($apiKey, [[ 'parts' => [['text' => $textPrompt]] ]]);
$textReply = sanitizeReply(extractGeminiReply($textResp));

if (isPlatformQuestion($message) && ($textReply === '' || str_contains($textReply, 'غير مذكور في محتوى هذا الدرس'))) {
    $platformFallback = tryPlatformRulesAnswer($message);
    if ($platformFallback !== null) {
        out(['reply' => $platformFallback, 'mode' => 'text', 'source' => 'direct']);
    }
}

if ($textReply === '') {
    $textReply = getRuleBasedReply($message, $lessonCtx, (string)$chatCtx['last_topic']);
    $source = 'fallback';
} else {
    $source = (($lessonCtx['pdf_text'] ?? '') !== '' ? 'lesson' : 'general');
}

out(['reply' => $textReply, 'mode' => 'text', 'source' => $source]);

/* Helpers */

function out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function callGemini(string $apiKey, array $contents): ?array {
    $models = ['gemini-2.5-flash', 'gemini-2.0-flash'];

    $payload = json_encode([
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.4,
            'topP' => 1,
            'maxOutputTokens' => 800
        ]
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        error_log('Gemini payload json_encode failed');
        return null;
    }

    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 25,
        ]);

        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $curlErr !== '') {
            error_log("Gemini cURL error on {$model}: {$curlErr}");
            continue;
        }

        if ($httpCode === 200) {
            $decoded = json_decode((string)$resp, true);
            return is_array($decoded) ? $decoded : null;
        }

        error_log("Gemini API Error on {$model} HTTP {$httpCode}: {$resp}");
    }

    return null;
}

function detectMimeType(string $path, string $fallback = 'audio/webm'): string {
    $fallback = trim((string)explode(';', $fallback)[0]);

    $aliasMap = [
        'audio/x-m4a' => 'audio/mp4',
        'audio/m4a'   => 'audio/mp4',
        'audio/mp3'   => 'audio/mpeg',
    ];
    if (isset($aliasMap[$fallback])) $fallback = $aliasMap[$fallback];

    $mime = $fallback !== '' ? $fallback : 'audio/webm';

    if ($path !== '' && is_file($path)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? (string)finfo_file($finfo, $path) : '';
        if ($finfo) finfo_close($finfo);

        $detected = trim((string)explode(';', $detected)[0]);
        if ($detected !== '') $mime = $detected;
    }

    if (isset($aliasMap[$mime])) $mime = $aliasMap[$mime];

    $supported = ['audio/webm', 'audio/wav', 'audio/mpeg', 'audio/ogg', 'audio/aac', 'audio/flac', 'audio/mp4'];

    if (!in_array($mime, $supported, true)) {
        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['m4a', 'mp4'], true)) return 'audio/mp4';
        if ($ext === 'mp3') return 'audio/mpeg';
        if (in_array($ext, ['ogg', 'oga'], true)) return 'audio/ogg';
        if ($ext === 'wav') return 'audio/wav';
        return in_array($fallback, $supported, true) ? $fallback : 'audio/webm';
    }

    return $mime;
}

function extractGeminiReply(?array $resp): string {
    if (!is_array($resp)) return '';
    return trim((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''));
}

function sanitizeReply(string $reply): string {
    $reply = trim($reply);
    if ($reply === '') return '';

    $patterns = [
        '/^\s*(?:أهلا|أهلاً|أهلًا|مرحبا|مرحبًا)\s*[^.!؟\n]{0,180}[.!؟]\s*/u',
        '/^\s*يسعدني\s+[^.!؟\n]{0,220}[.!؟]\s*/u',
        '/^\s*أنا\s+مساعدك[^.!؟\n]{0,220}[.!؟]\s*/u',
    ];

    foreach ($patterns as $p) {
        $reply = preg_replace($p, '', $reply) ?? $reply;
    }

    $reply = preg_replace('/\n{3,}/u', "\n\n", $reply) ?? $reply;
    return trim($reply);
}

function normalizeArabicQuestion(string $q): string {
    $q = trim($q);
    if ($q === '') return $q;

    $q = str_replace(['أ', 'إ', 'آ'], 'ا', $q);

    $map = [
        'هنوان' => 'عنوان',
        'عنواان' => 'عنوان',
        'عنون' => 'عنوان',
        'ادرس' => 'الدرس',
        'الاول' => 'الأول',
        'ماهو' => 'ما هو',
        'ماهي' => 'ما هي',
    ];
    $q = str_ireplace(array_keys($map), array_values($map), $q);

    $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
    return trim($q);
}

function detectTopic(string $msg): ?string {
    $m = mb_strtolower($msg);

    if (preg_match('/(لعب|العاب|ألعاب|مغامرة|محاول|نقاط|قوانين|اكتشاف العلماء|تنافس)/u', $m)) return 'platform_games';
    if (preg_match('/(درس|عنوان الدرس|محتوى الدرس|pdf|قاعدة|نحو)/u', $m)) return 'lesson';
    return null;
}

function resolveEllipsisByContext(string $msg, string $lastTopic): ?string {
    $m = trim(mb_strtolower($msg));
    $short = ['اليوم؟', 'اليوم', 'طيب اليوم؟', 'كم اليوم؟', 'يعني اليوم؟', 'هنا؟', 'هنا'];

    if (in_array($m, $short, true) && $lastTopic === 'platform_games') return 'كم عدد المحاولات اليومية في ألعاب الدرس؟';
    if ($m === 'أقصد من ألعاب الدرس هنا' || $m === 'اقصد من العاب الدرس هنا' || $m === 'أقصد ألعاب الدرس هنا') return 'ما نظام ألعاب الدرس هنا؟';
    if (($m === 'عندي سؤال' || $m === 'سؤال') && $lastTopic === 'platform_games') return 'ما قوانين اللعب في ألعاب الدرس؟';

    return null;
}

function isPlatformQuestion(string $msg): bool {
    $m = mb_strtolower($msg);

    $keys = ['لعب', 'العب', 'ألعب', 'العاب', 'ألعاب', 'مغامرة', 'نقاط', 'محاول', 'يومي', 'قوانين', 'اكتشاف العلماء', 'علماء', 'تنافس', 'التنافس', 'طريقة اللعب', 'الموقع', 'اركان', 'أركان'];
    foreach ($keys as $k) {
        if (str_contains($m, $k)) return true;
    }

    if ((str_contains($m, 'مرات') || str_contains($m, 'مرة')) && str_contains($m, 'اليوم')) return true;
    if (str_contains($m, '10 مرات') || str_contains($m, 'عشر مرات')) return true;

    return false;
}

function enforceGamePolicy(string $msg, string $lessonName = ''): ?string {
    $m = mb_strtolower(trim($msg));

    $solveWords = ['حل', 'الجواب النهائي', 'الإجابة النهائية', 'اعطني الحل', 'اعطني الاجابة', 'جاوب بدالي', 'أعطني الإجابة', 'اختبرني واعطني الجواب'];
    $evalWords = ['اختبار', 'كويز', 'quiz', 'صح ام خطا', 'اختيار من متعدد', 'سؤال اللعبة', 'سؤال المغامرة'];

    $hasSolveWord = false;
    foreach ($solveWords as $w) {
        if (str_contains($m, mb_strtolower($w))) { $hasSolveWord = true; break; }
    }

    $hasEvalWord = false;
    foreach ($evalWords as $w) {
        if (str_contains($m, mb_strtolower($w))) { $hasEvalWord = true; break; }
    }

    if (!($hasSolveWord && $hasEvalWord)) return null;

    $lessonPart = $lessonName !== '' ? " في درس «{$lessonName}»" : '';
    return "أفهم رغبتك في المساعدة{$lessonPart}، لكن لا أستطيع تقديم الحل النهائي لأسئلة الألعاب أو الاختبارات.\n"
         . "بدلًا من ذلك أستطيع مساعدتك عبر:\n"
         . "1) شرح القاعدة المرتبطة بالسؤال.\n"
         . "2) إعطائك خطوات التفكير.\n"
         . "3) تقديم مثال مشابه للتدريب.\n"
         . "أرسل نص السؤال وسأرشدك خطوة بخطوة دون إعطاء الإجابة النهائية.";
}

function getPlatformRules(): array {
    return [
        'questions_per_adventure' => 5,
        'daily_attempts' => 5,
        'max_points' => 350,
        'completed_threshold' => '5/5',
        'incomplete_rule' => 'أي نتيجة أقل من 5/5',
        'goal' => 'اكتشاف علماء العربية بصورة تنافسية مع الطلاب الآخرين'
    ];
}

function tryPlatformRulesAnswer(string $msg): ?string {
    $m = mb_strtolower(trim($msg));
    $r = getPlatformRules();

    if (str_contains($m, '10 مرات') || str_contains($m, 'عشر مرات')) {
        return "لا، لا يمكنك اللعب 10 مرات يوميًا. الحد اليومي هو {$r['daily_attempts']} محاولات لكل مغامرة.";
    }

    if (
        preg_match('/كم\s*(مرة|مرات|محاولة|محاولات).*(اليوم|باليوم)/u', $m) ||
        preg_match('/مسموح.*(لعب|ألعب|العب).*(اليوم|باليوم)/u', $m) ||
        str_contains($m, 'كم مرة مسموح') ||
        str_contains($m, 'كم مره مسموح') ||
        str_contains($m, 'المحاولات اليومية')
    ) {
        return "المسموح يوميًا هو {$r['daily_attempts']} محاولات لكل مغامرة.";
    }

    if (str_contains($m, 'غير محدود') || str_contains($m, 'بدون حد')) {
        return "الصحيح: المحاولات ليست غير محدودة. الحد اليومي هو {$r['daily_attempts']} محاولات لكل مغامرة.";
    }

    if (
        str_contains($m, 'نظام الالعاب') || str_contains($m, 'نظام الألعاب') ||
        str_contains($m, 'قوانين اللعبة') || str_contains($m, 'قوانين الألعاب') ||
        str_contains($m, 'طريقة اللعب') || str_contains($m, 'كيف العب') || str_contains($m, 'كيف ألعب') ||
        str_contains($m, 'العاب الدرس') || str_contains($m, 'ألعاب الدرس')
    ) {
        return "قوانين ألعاب الدرس في المنصة:\n"
             . "• {$r['questions_per_adventure']} أسئلة في كل مغامرة.\n"
             . "• {$r['daily_attempts']} محاولات يوميًا.\n"
             . "• حتى {$r['max_points']} نقطة.\n"
             . "• مكتملة 100% عند {$r['completed_threshold']}.\n"
             . "• أقل من {$r['completed_threshold']} = {$r['incomplete_rule']}.\n"
             . "• الهدف: {$r['goal']}.";
    }

    if (str_contains($m, 'كم سؤال') || str_contains($m, 'عدد الاسئلة') || str_contains($m, 'عدد الأسئلة')) {
        return "عدد الأسئلة في كل مغامرة هو {$r['questions_per_adventure']} أسئلة.";
    }

    if (
        str_contains($m, 'كم نقطة') ||
        str_contains($m, 'الحد الاعلى للنقاط') ||
        str_contains($m, 'الحد الأعلى للنقاط') ||
        str_contains($m, 'اقصى نقاط') ||
        str_contains($m, 'أقصى نقاط')
    ) {
        return "يمكنك الحصول على حتى {$r['max_points']} نقطة.";
    }

    if (
        str_contains($m, 'مكتملة') ||
        str_contains($m, 'غير مكتملة') ||
        str_contains($m, '100%') ||
        str_contains($m, '5/5')
    ) {
        return "مكتملة 100% تعني حل جميع الأسئلة ({$r['completed_threshold']})، وأي نتيجة أقل من {$r['completed_threshold']} تُعد غير مكتملة 100%.";
    }

    if (
        str_contains($m, 'اكتشف العلماء') ||
        str_contains($m, 'اكتشاف العلماء') ||
        str_contains($m, 'هدف الالعاب') ||
        str_contains($m, 'هدف الألعاب') ||
        str_contains($m, 'تنافس') ||
        str_contains($m, 'التنافس')
    ) {
        return "هدف الألعاب التعليمية هو {$r['goal']}، مع تحفيز التقدم بالنقاط والتنافس الإيجابي.";
    }

    if (str_contains($m, 'ماذا يحدث عند الفشل') || str_contains($m, 'اذا فشلت') || str_contains($m, 'إذا فشلت')) {
        return "عند عدم تحقيق {$r['completed_threshold']} تُسجَّل النتيجة كـ «غير مكتملة 100%»، ويمكنك إعادة المحاولة ضمن الحد اليومي ({$r['daily_attempts']} محاولات).";
    }

    if (
        str_contains($m, 'كيف استخدم الموقع') ||
        str_contains($m, 'طريقة استخدام الموقع') ||
        str_contains($m, 'كيف ابدا') ||
        str_contains($m, 'كيف أبدأ')
    ) {
        return "طريقة اللعب بالموقع:\n"
             . "1) افتح الدرس المطلوب.\n"
             . "2) راجع شرح الدرس وملف PDF.\n"
             . "3) ابدأ مغامرة اللعبة (5 أسئلة).\n"
             . "4) أجب بدقة لتحقيق 5/5.\n"
             . "5) تابع نقاطك وحاول يوميًا ضمن 5 محاولات لاكتشاف العلماء.";
    }

    return null;
}

function tryDirectAnswer(PDO $db, string $msg, array $lessonCtx): ?string {
    $m = mb_strtolower(trim($msg));
    $idCol = detectLessonsIdColumn($db);

    if (
        (int)($lessonCtx['id'] ?? 0) > 0 &&
        (
            str_contains($m, 'عنوان هذا الدرس') ||
            str_contains($m, 'اسم هذا الدرس') ||
            str_contains($m, 'عنوان الدرس الحالي') ||
            str_contains($m, 'اسم الدرس الحالي')
        )
    ) {
        $name = trim((string)($lessonCtx['name'] ?? ''));
        return $name !== '' ? "عنوان الدرس الحالي هو: {$name}." : 'تعذر تحديد عنوان الدرس الحالي.';
    }

    $wantTitle = str_contains($m, 'عنوان الدرس') || str_contains($m, 'اسم الدرس');
    if ($wantTitle) {
        $offset = null;

        if (str_contains($m, 'الأول') || str_contains($m, 'الاول') || preg_match('/\b1\b/u', $m)) $offset = 0;
        elseif (str_contains($m, 'الثاني') || preg_match('/\b2\b/u', $m)) $offset = 1;
        elseif (str_contains($m, 'الثالث') || preg_match('/\b3\b/u', $m)) $offset = 2;
        elseif (str_contains($m, 'الرابع') || preg_match('/\b4\b/u', $m)) $offset = 3;

        if ($offset !== null) {
            $sql = "SELECT name FROM lessons ORDER BY {$idCol} ASC LIMIT 1 OFFSET {$offset}";
            $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

            $ordinals = ['الأول', 'الثاني', 'الثالث', 'الرابع'];
            $ord = $ordinals[$offset] ?? 'المطلوب';

            if ($row && !empty($row['name'])) {
                return "عنوان الدرس {$ord} هو: {$row['name']}.";
            }
            return "لا يوجد درس {$ord} متاح حاليًا.";
        }
    }

    if (str_contains($m, 'كم عدد الدروس') || str_contains($m, 'عدد الدروس')) {
        $row = $db->query("SELECT COUNT(*) AS c FROM lessons")->fetch(PDO::FETCH_ASSOC);
        $count = (int)($row['c'] ?? 0);
        return "عدد الدروس المتاحة حاليًا: {$count}.";
    }

    return null;
}

function detectLessonsIdColumn(PDO $db): string {
    try {
        $colsStmt = $db->query("SHOW COLUMNS FROM lessons");
        $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $fields = array_map(fn($r) => strtolower((string)($r['Field'] ?? '')), $cols);

        if (in_array('id', $fields, true)) return 'id';
        if (in_array('lesson_id', $fields, true)) return 'lesson_id';
    } catch (\Throwable $e) {}
    return 'id';
}

function resolveLessonContext(PDO $db, int $lessonId): array {
    $ctx = [
        'id' => 0,
        'name' => '',
        'pdf_url' => '',
        'pdf_path' => '',
        'pdf_text' => ''
    ];

    if ($lessonId <= 0) return $ctx;

    $idCol = detectLessonsIdColumn($db);

    $columns = [];
    $stmtCols = $db->query("SHOW COLUMNS FROM lessons");
    if ($stmtCols) {
        $columns = array_map(
            fn($r) => strtolower((string)($r['Field'] ?? '')),
            $stmtCols->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    if (!in_array('name', $columns, true) || !in_array('pdf_url', $columns, true)) return $ctx;

    $sql = "SELECT {$idCol} AS lesson_id, name, pdf_url FROM lessons WHERE {$idCol} = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lesson) return $ctx;

    $ctx['id']      = (int)($lesson['lesson_id'] ?? 0);
    $ctx['name']    = trim((string)($lesson['name'] ?? ''));
    $ctx['pdf_url'] = trim((string)($lesson['pdf_url'] ?? ''));

    if ($ctx['pdf_url'] === '' || !defined('UPLOAD_DIR')) return $ctx;

    if (preg_match('~^https?://~i', $ctx['pdf_url'])) return $ctx;

    $candidates = [
        rtrim((string)UPLOAD_DIR, '/\\') . '/pdfs/' . basename($ctx['pdf_url']),
        rtrim((string)UPLOAD_DIR, '/\\') . '/' . ltrim($ctx['pdf_url'], '/\\'),
        dirname(__DIR__) . '/' . ltrim($ctx['pdf_url'], '/\\'),
    ];

    foreach ($candidates as $p) {
        if (is_file($p) && is_readable($p)) {
            $ctx['pdf_path'] = $p;
            break;
        }
    }

    if ($ctx['pdf_path'] !== '' && function_exists('extractPdfText')) {
        $text = trim((string)extractPdfText($ctx['pdf_path']));
        $ctx['pdf_text'] = mb_substr($text, 0, 18000);
    }

    return $ctx;
}

function buildLessonBlockForPrompt(array $lessonCtx): string {
    $name = trim((string)($lessonCtx['name'] ?? ''));
    $text = trim((string)($lessonCtx['pdf_text'] ?? ''));

    if ($name === '' && $text === '') return '';

    $block = "[سياق الدرس]\n";
    if ($name !== '') $block .= "عنوان الدرس: {$name}\n";

    if ($text !== '') {
        $block .= "\n[نص الدرس من PDF]\n{$text}";
    } else {
        $block .= "\n[ملاحظة]\nلا يتوفر نص PDF قابل للاستخراج لهذا الدرس.";
    }

    return $block;
}

function getRuleBasedReply(string $msg, array $lessonCtx = [], string $lastTopic = ''): string {
    $msg = trim($msg);
    $m = mb_strtolower($msg);

    if (isPlatformQuestion($msg) || $lastTopic === 'platform_games') {
        $platform = tryPlatformRulesAnswer($msg);
        if ($platform !== null) return $platform;
    }

    if (str_contains($m, 'نحو') || str_contains($m, 'قواعد') || str_contains($m, 'اعراب') || str_contains($m, 'إعراب')) {
        return 'حدّد الجملة أو القاعدة التي تريد شرحها، وسأشرحها لك خطوة بخطوة مع مثال تطبيقي.';
    }

    if (str_contains($m, 'الدرس') || str_contains($m, 'عنوان') || str_contains($m, 'محتوى') || str_contains($m, 'pdf')) {
        $lessonName = trim((string)($lessonCtx['name'] ?? ''));
        if ($lessonName !== '') {
            return "بالنسبة لدرس «{$lessonName}»، اكتب سؤالك بشكل مباشر (مثلاً: ما الفكرة الرئيسة؟ أو اشرح القاعدة الأولى).";
        }
        return 'اكتب رقم الدرس أو سؤالك عن محتوى الدرس وسأجيبك مباشرة.';
    }

    if (mb_strlen($m) < 3 || in_array($m, ['طيب', 'تمام', 'اوكي', '...', '؟', '?'], true)) {
        return 'اكتب سؤالك بشكل أوضح قليلًا، وأنا سأجيبك مباشرة.';
    }

    $fallbacks = [
        "فهمت سؤالك: «{$msg}». هل تريد جوابًا مختصرًا أم شرحًا خطوة بخطوة؟",
        "أستطيع مساعدتك في هذا السؤال. هل تفضّل مثالًا عمليًا أم شرحًا مباشرًا؟",
        "سأساعدك بالتأكيد. اكتب تفاصيل أكثر قليلًا (مثال/جملة/سياق) لأعطيك جوابًا أدق."
    ];

    return $fallbacks[array_rand($fallbacks)];
}

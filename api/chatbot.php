<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['reply' => 'يرجى استخدام طريقة POST']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$message = trim($body['message'] ?? '');

if (empty($message)) {
    echo json_encode(['reply' => 'الرسالة فارغة.']);
    exit;
}

$apiKey = GEMINI_API_KEY;

if (empty($apiKey)) {
    // Fallback to rule-based responses
    $reply = getRuleBasedReply($message);
    echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}

// Call Gemini via cURL
$systemPrompt = "أنت مساعد تعليمي ذكي في تطبيق «المساعد الذّكاليّ» لتعليم العربية لغير الناطقين بها. دورك: توجيه الطلاب لاستخدام التطبيق، تقديم نصائح تربوية، والإجابة على أسئلة اللغة العربية والنحو بأسلوب بسيط ومشجع. أجب دائماً باللغة العربية الفصحى بأسلوب ودود.";
$prompt       = $systemPrompt . "\n\nسؤال الطالب: " . $message;

$url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);
$payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);

if (!function_exists('curl_init')) {
    $reply = getRuleBasedReply($message);
} else {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp    = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp !== false && $httpCode === 200) {
        $data  = json_decode($resp, true);
        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? getRuleBasedReply($message);
    } else {
        $reply = getRuleBasedReply($message);
    }
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);

function getRuleBasedReply(string $msg): string {
    $msg = mb_strtolower($msg);
    if (str_contains($msg, 'نقط') || str_contains($msg, 'نقاط'))
        return 'النقاط تُكتسب من خلال إكمال ألعاب المغامرة بنجاح. كلما فزت بسرعة وبمحاولات أقل، حصلت على نقاط أكثر! 🌟';
    if (str_contains($msg, 'لعب') || str_contains($msg, 'مغامر'))
        return 'لعبة المغامرة موجودة في كل درس. اختر درساً مفتوحاً، ثم انقر على "لعبة المغامرة" وستبدأ رحلتك! 🎮';
    if (str_contains($msg, 'بودكاست') || str_contains($msg, 'صوت'))
        return 'ستجد البودكاست في ركن "البودكاست الصوتي" داخل صفحة كل درس. استمع وتعلّم! 🎧';
    if (str_contains($msg, 'عالم') || str_contains($msg, 'علماء'))
        return 'علماء النحو مخفيون! اكسبهم بالفوز في الألعاب وستجدهم في صفحة "نقاطي وعلمائي" 📜';
    if (str_contains($msg, 'سجل') || str_contains($msg, 'تسجيل'))
        return 'للتسجيل انقر على "حساب جديد" في الصفحة الرئيسية وأدخل بياناتك. 📝';
    if (str_contains($msg, 'نحو') || str_contains($msg, 'قواعد'))
        return 'النحو العربي علم جميل! ابدأ بدروس المبتدأ والخبر ثم تقدم خطوة خطوة. التطبيق صمّم خصيصاً لمساعدتك 💪';
    return 'شكراً لسؤالك! يمكنني مساعدتك في التنقل بالتطبيق، الإجابة على أسئلة النحو، أو تقديم نصائح تعليمية. ما الذي تودّ معرفته؟ 🌿';
}

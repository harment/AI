<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$studentId = (int)$_SESSION['student_id'];
$db        = getDB();

// ========== GET: التحقق من عدد المحاولات المتبقية ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lessonId = (int)($_GET['lesson_id'] ?? 0);
    
    if (!$lessonId) {
        jsonResponse(['error' => 'lesson_id required'], 400);
    }
    
    // حساب عدد المحاولات في آخر 24 ساعة
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempts_count 
        FROM student_games 
        WHERE student_id = ? 
        AND lesson_id = ? 
        AND played_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$studentId, $lessonId]);
    $result = $stmt->fetch();
    
    $attemptsUsed = (int)$result['attempts_count'];
    $maxAttempts = 3;
    $attemptsRemaining = max(0, $maxAttempts - $attemptsUsed);
    
    // الحصول على وقت آخر محاولة
    $lastAttemptStmt = $db->prepare("
        SELECT played_at 
        FROM student_games 
        WHERE student_id = ? AND lesson_id = ? 
        ORDER BY played_at DESC 
        LIMIT 1
    ");
    $lastAttemptStmt->execute([$studentId, $lessonId]);
    $lastAttempt = $lastAttemptStmt->fetch();
    
    $nextAvailableTime = null;
    if ($attemptsRemaining === 0 && $lastAttempt) {
        $lastTime = strtotime($lastAttempt['played_at']);
        $nextAvailableTime = date('Y-m-d H:i:s', $lastTime + (24 * 60 * 60));
    }
    
    jsonResponse([
        'attempts_used' => $attemptsUsed,
        'attempts_remaining' => $attemptsRemaining,
        'max_attempts' => $maxAttempts,
        'can_play' => $attemptsRemaining > 0,
        'next_available' => $nextAvailableTime
    ]);
}

// ========== POST: حفظ نتيجة اللعبة ==========
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

$body           = json_decode(file_get_contents('php://input'), true);
$lessonId       = (int)($body['lesson_id'] ?? 0);
$points         = (int)($body['points'] ?? 0);
$scholarId      = $body['scholar_id'] ? (int)$body['scholar_id'] : null;
$completed      = (int)($body['completed'] ?? 0);
$gameMode       = $body['game_mode'] ?? 'mountain';
$questionResults = $body['question_results'] ?? [];

if (!$lessonId) {
    jsonResponse(['error' => 'lesson_id required'], 400);
}

// التحقق من أن الدرس موجود ومفتوح
$lesson = $db->prepare("SELECT id FROM lessons WHERE id=? AND is_open=1");
$lesson->execute([$lessonId]);
if (!$lesson->fetch()) {
    jsonResponse(['error' => 'Lesson not found or closed'], 404);
}

// التحقق من عدد المحاولات المتبقية
$checkAttempts = $db->prepare("
    SELECT COUNT(*) as attempts_count 
    FROM student_games 
    WHERE student_id = ? 
    AND lesson_id = ? 
    AND played_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$checkAttempts->execute([$studentId, $lessonId]);
$attemptsData = $checkAttempts->fetch();

if ((int)$attemptsData['attempts_count'] >= 3) {
    jsonResponse([
        'error' => 'تجاوزت الحد الأقصى للمحاولات اليومية (3 محاولات)',
        'attempts_exceeded' => true
    ], 429);
}

// حفظ سجل اللعبة
$gameInsert = $db->prepare("
    INSERT INTO student_games 
    (student_id, lesson_id, points_earned, scholar_id, completed, game_mode, played_at) 
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$gameInsert->execute([$studentId, $lessonId, $points, $scholarId, $completed, $gameMode]);
$gameId = $db->lastInsertId();

// حفظ تفاصيل إجابات الأسئلة
if (!empty($questionResults) && is_array($questionResults)) {
    $questionStmt = $db->prepare("
        INSERT INTO question_attempts 
        (student_id, question_id, lesson_id, is_correct, attempts_count, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($questionResults as $result) {
        if (isset($result['question_id'])) {
            $questionStmt->execute([
                $studentId,
                (int)$result['question_id'],
                $lessonId,
                (int)($result['is_correct'] ?? 0),
                (int)($result['attempts'] ?? 1)
            ]);
        }
    }
}

// تحديث نقاط الطالب
if ($points > 0 && $completed) {
    $db->prepare("UPDATE students SET points = points + ? WHERE id = ?")->execute([$points, $studentId]);
}

// إضافة العالم المكتشف
if ($scholarId && $completed) {
    try {
        $db->prepare("INSERT IGNORE INTO student_scholars (student_id, scholar_id) VALUES (?, ?)")
           ->execute([$studentId, $scholarId]);
    } catch (PDOException $e) {}
    
    logActivity($studentId, $lessonId, 'game_win', "نقاط: $points | نمط: $gameMode", 0);
} else {
    logActivity($studentId, $lessonId, 'game_play', "نمط: $gameMode", 0);
}

// حساب المحاولات المتبقية بعد الحفظ
$newAttemptsCheck = $db->prepare("
    SELECT COUNT(*) as attempts_count 
    FROM student_games 
    WHERE student_id = ? 
    AND lesson_id = ? 
    AND played_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$newAttemptsCheck->execute([$studentId, $lessonId]);
$newAttemptsData = $newAttemptsCheck->fetch();
$attemptsRemaining = max(0, 3 - (int)$newAttemptsData['attempts_count']);

jsonResponse([
    'success' => true,
    'points_added' => $points,
    'game_id' => $gameId,
    'attempts_remaining' => $attemptsRemaining,
    'scholar_discovered' => $scholarId ? true : false
]);

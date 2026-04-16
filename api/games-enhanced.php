<?php
// توافق رجعي: بعض النسخ القديمة من الواجهة تستدعي هذا المسار
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// بعض النسخ القديمة تنفذ فحص محاولات يومي عبر GET قبل الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = getDB();
    $lessonId = (int)($_GET['lesson_id'] ?? 0);
    $maxAttempts = 5;
    $remaining = $maxAttempts;
    $retryAfter = null;

    if ($lessonId > 0) {
        $countStmt = $db->prepare("
            SELECT COUNT(*) FROM activity_log
            WHERE student_id=?
              AND lesson_id=?
              AND action IN ('game_play','game_win')
              AND created_at >= (NOW() - INTERVAL 24 HOUR)
        ");
        $countStmt->execute([(int)$_SESSION['student_id'], $lessonId]);
        $used = (int)$countStmt->fetchColumn();
        $remaining = max(0, $maxAttempts - $used);

        if ($remaining === 0) {
            $oldestStmt = $db->prepare("
                SELECT created_at FROM activity_log
                WHERE student_id=?
                  AND lesson_id=?
                  AND action IN ('game_play','game_win')
                  AND created_at >= (NOW() - INTERVAL 24 HOUR)
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $oldestStmt->execute([(int)$_SESSION['student_id'], $lessonId]);
            $oldest = $oldestStmt->fetchColumn();
            if ($oldest) {
                $retryAfter = (new DateTime((string)$oldest))->modify('+24 hours')->format(DateTime::ATOM);
            }
        }
    }

    jsonResponse([
        'success'            => true,
        'can_play'           => $remaining > 0,
        'max_attempts'       => $maxAttempts,
        'remaining_attempts' => $remaining,
        'attempts_left'      => $remaining,
        'retry_after'        => $retryAfter,
        'retry_after_text'   => '',
    ]);
}

// POST (حفظ نتيجة اللعبة) يبقى على المسار الحالي
require __DIR__ . '/games.php';

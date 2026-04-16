<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$studentId = (int)$_SESSION['student_id'];
$db        = getDB();
const DAILY_GAME_ATTEMPTS_LIMIT = 5;
const DAILY_GAME_ATTEMPTS_WINDOW_HOURS = 24;

function dbTableHasColumn(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function getRecentLessonGameAttempts(PDO $db, int $studentId, int $lessonId, int $hours = DAILY_GAME_ATTEMPTS_WINDOW_HOURS): int
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM activity_log
        WHERE student_id = ?
          AND lesson_id = ?
          AND action IN ('game_play', 'game_win')
          AND created_at >= (NOW() - INTERVAL {$hours} HOUR)
    ");
    $stmt->execute([$studentId, $lessonId]);
    return (int)$stmt->fetchColumn();
}

function getRetryAfterForLessonGame(PDO $db, int $studentId, int $lessonId, int $hours = DAILY_GAME_ATTEMPTS_WINDOW_HOURS): ?string
{
    $stmt = $db->prepare("
        SELECT created_at
        FROM activity_log
        WHERE student_id = ?
          AND lesson_id = ?
          AND action IN ('game_play', 'game_win')
          AND created_at >= (NOW() - INTERVAL {$hours} HOUR)
        ORDER BY created_at ASC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $lessonId]);
    $oldest = $stmt->fetchColumn();
    if (!$oldest) {
        return null;
    }
    $retryAt = (new DateTime((string)$oldest))->modify("+{$hours} hours");
    return $retryAt->format(DateTime::ATOM);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId  = (int)($body['lesson_id'] ?? 0);
$points    = (int)($body['points'] ?? 0);
$scholarId = !empty($body['scholar_id']) ? (int)$body['scholar_id'] : null;
$completed = (int)($body['completed'] ?? 0);

if (!$lessonId) {
    jsonResponse(['error' => 'lesson_id required'], 400);
}

// Check lesson exists (and open when schema supports is_open)
$hasLessonOpen = dbTableHasColumn($db, 'lessons', 'is_open');
$lessonSql = $hasLessonOpen
    ? "SELECT id FROM lessons WHERE id=? AND is_open=1"
    : "SELECT id FROM lessons WHERE id=?";
$lesson = $db->prepare($lessonSql);
$lesson->execute([$lessonId]);
if (!$lesson->fetch()) {
    jsonResponse(['error' => 'Lesson not found or closed'], 404);
}

$recentAttempts = getRecentLessonGameAttempts($db, $studentId, $lessonId);
if ($recentAttempts >= DAILY_GAME_ATTEMPTS_LIMIT) {
    jsonResponse([
        'error'              => 'لقد استنفدت محاولاتك اليومية لهذا الدرس.',
        'max_attempts'       => DAILY_GAME_ATTEMPTS_LIMIT,
        'remaining_attempts' => 0,
        'attempts_left'      => 0,
        'retry_after'        => getRetryAfterForLessonGame($db, $studentId, $lessonId),
    ], 429);
}

// One row per student+lesson: upsert approach (compatible with older schemas)
$hasCompleted = dbTableHasColumn($db, 'student_games', 'completed');
$hasAttempts  = dbTableHasColumn($db, 'student_games', 'attempts');
$hasScholar   = dbTableHasColumn($db, 'student_games', 'scholar_id');

$selectCols = ['id', 'points_earned'];
if ($hasCompleted) {
    $selectCols[] = 'completed';
}
$existing = $db->prepare("SELECT " . implode(', ', $selectCols) . " FROM student_games WHERE student_id=? AND lesson_id=? ORDER BY id DESC LIMIT 1");
$existing->execute([$studentId, $lessonId]);
$existing = $existing->fetch();

$pointsAdded = 0;
if (!$existing) {
    $insertCols = ['student_id', 'lesson_id', 'points_earned'];
    $insertVals = [$studentId, $lessonId, $completed ? $points : 0];
    if ($hasScholar) {
        $insertCols[] = 'scholar_id';
        $insertVals[] = $scholarId;
    }
    if ($hasCompleted) {
        $insertCols[] = 'completed';
        $insertVals[] = $completed;
    }
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $db->prepare("INSERT INTO student_games (" . implode(', ', $insertCols) . ") VALUES ($placeholders)")
       ->execute($insertVals);
    if ($completed && $points > 0) {
        $pointsAdded = $points;
    }
} else {
    if ($completed) {
        // Every completed replay adds points
        $setParts = ['points_earned=points_earned+?'];
        $updateVals = [$points];
        if ($hasScholar) {
            $setParts[] = 'scholar_id=?';
            $updateVals[] = $scholarId;
        }
        if ($hasCompleted) {
            $setParts[] = 'completed=1';
        }
        if ($hasAttempts) {
            $setParts[] = 'attempts=attempts+1';
        }
        $updateVals[] = $existing['id'];
        $db->prepare("UPDATE student_games SET " . implode(', ', $setParts) . " WHERE id=?")
           ->execute($updateVals);
        $pointsAdded = $points;
    } elseif ($hasAttempts) {
        // Loss replay: increment attempts only
        $db->prepare("UPDATE student_games SET attempts=attempts+1 WHERE id=?")->execute([$existing['id']]);
    }
}

// Award points to student (only on first win)
if ($pointsAdded > 0) {
    $db->prepare("UPDATE students SET points=points+? WHERE id=?")->execute([$pointsAdded, $studentId]);
}

// Scholar discovery (only once per student+scholar)
if ($scholarId && $completed) {
    try {
        $db->prepare("INSERT IGNORE INTO student_scholars (student_id, scholar_id) VALUES (?,?)")->execute([$studentId, $scholarId]);
    } catch (PDOException $e) {}
}

// Activity log
if ($completed) {
    logActivity($studentId, $lessonId, 'game_win', "نقاط: $pointsAdded", 0);
} else {
    logActivity($studentId, $lessonId, 'game_play', '', 0);
}

$remainingAttempts = max(0, DAILY_GAME_ATTEMPTS_LIMIT - ($recentAttempts + 1));
jsonResponse([
    'success'            => true,
    'points_added'       => $pointsAdded,
    'max_attempts'       => DAILY_GAME_ATTEMPTS_LIMIT,
    'remaining_attempts' => $remainingAttempts,
    'attempts_left'      => $remainingAttempts,
]);

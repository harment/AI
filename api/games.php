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
const MAX_ARRAY_TRACK_ITEMS = 200;

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

function dbTableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function normalizeIntArray($value, int $maxItems = MAX_ARRAY_TRACK_ITEMS): array
{
    if (!is_array($value)) {
        return [];
    }
    $result = [];
    foreach ($value as $item) {
        $num = (int)$item;
        if ($num <= 0) {
            continue;
        }
        $result[$num] = $num;
        if (count($result) >= $maxItems) {
            break;
        }
    }
    return array_values($result);
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
$gameMode  = trim((string)($body['game_mode'] ?? 'unknown'));
$totalQuestions = max(0, (int)($body['total_questions'] ?? 0));
$completedQuestions = max(0, (int)($body['completed_questions'] ?? 0));
$incompleteQuestions = max(0, (int)($body['incomplete_questions'] ?? 0));
$correctQuestionNumbers = normalizeIntArray($body['correct_question_numbers'] ?? []);
$wrongQuestionNumbers   = normalizeIntArray($body['wrong_question_numbers'] ?? []);
$selectedQuestionIds    = normalizeIntArray($body['selected_question_ids'] ?? []);
$questionOutcomesRaw    = is_array($body['question_outcomes'] ?? null) ? $body['question_outcomes'] : [];
$durationSeconds = max(0, (int)($body['duration_seconds'] ?? 0));
$endedEarly = !empty($body['ended_early']) ? 1 : 0;

if (!$lessonId) {
    jsonResponse(['error' => 'lesson_id required'], 400);
}

if ($totalQuestions <= 0) {
    $totalQuestions = max(
        count($selectedQuestionIds),
        count($correctQuestionNumbers) + count($wrongQuestionNumbers),
        $completedQuestions + $incompleteQuestions
    );
}
if ($completedQuestions + $incompleteQuestions > $totalQuestions) {
    $incompleteQuestions = max(0, $totalQuestions - $completedQuestions);
}
if ($totalQuestions > 0 && count($correctQuestionNumbers) > $totalQuestions) {
    $correctQuestionNumbers = array_slice($correctQuestionNumbers, 0, $totalQuestions);
}
if ($totalQuestions > 0 && count($wrongQuestionNumbers) > $totalQuestions) {
    $wrongQuestionNumbers = array_slice($wrongQuestionNumbers, 0, $totalQuestions);
}

$questionOutcomes = [];
foreach ($questionOutcomesRaw as $row) {
    if (!is_array($row)) {
        continue;
    }
    $questionOutcomes[] = [
        'question_number' => max(1, (int)($row['question_number'] ?? 0)),
        'question_id'     => max(0, (int)($row['question_id'] ?? 0)),
        'status'          => in_array(($row['status'] ?? ''), ['correct', 'wrong'], true) ? $row['status'] : 'unknown',
        'attempts_used'   => max(1, (int)($row['attempts_used'] ?? 1)),
    ];
    if (count($questionOutcomes) >= MAX_ARRAY_TRACK_ITEMS) {
        break;
    }
}

$analyticsPayload = [
    'game_mode' => $gameMode ?: 'unknown',
    'total_questions' => $totalQuestions,
    'completed_questions' => $completedQuestions,
    'incomplete_questions' => $incompleteQuestions,
    'correct_question_numbers' => $correctQuestionNumbers,
    'wrong_question_numbers' => $wrongQuestionNumbers,
    'selected_question_ids' => $selectedQuestionIds,
    'question_outcomes' => $questionOutcomes,
    'duration_seconds' => $durationSeconds,
    'ended_early' => $endedEarly,
];

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
    logActivity(
        $studentId,
        $lessonId,
        'game_win',
        json_encode(array_merge(['points_added' => $pointsAdded], $analyticsPayload), JSON_UNESCAPED_UNICODE),
        $durationSeconds
    );
} else {
    logActivity(
        $studentId,
        $lessonId,
        'game_play',
        json_encode(array_merge(['points_added' => 0], $analyticsPayload), JSON_UNESCAPED_UNICODE),
        $durationSeconds
    );
}

if (dbTableExists($db, 'game_attempt_analytics')) {
    $db->prepare("
        INSERT INTO game_attempt_analytics (
            student_id, lesson_id, game_mode, total_questions, completed_questions, incomplete_questions,
            correct_question_numbers_json, wrong_question_numbers_json, selected_question_ids_json, question_outcomes_json,
            score_correct, score_wrong, points_earned, completed, duration_seconds, ended_early
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $studentId,
        $lessonId,
        $gameMode ?: 'unknown',
        $totalQuestions,
        $completedQuestions,
        $incompleteQuestions,
        json_encode($correctQuestionNumbers, JSON_UNESCAPED_UNICODE),
        json_encode($wrongQuestionNumbers, JSON_UNESCAPED_UNICODE),
        json_encode($selectedQuestionIds, JSON_UNESCAPED_UNICODE),
        json_encode($questionOutcomes, JSON_UNESCAPED_UNICODE),
        count($correctQuestionNumbers),
        count($wrongQuestionNumbers),
        $pointsAdded,
        $completed ? 1 : 0,
        $durationSeconds,
        $endedEarly,
    ]);
}

$remainingAttempts = max(0, DAILY_GAME_ATTEMPTS_LIMIT - ($recentAttempts + 1));
jsonResponse([
    'success'            => true,
    'points_added'       => $pointsAdded,
    'max_attempts'       => DAILY_GAME_ATTEMPTS_LIMIT,
    'remaining_attempts' => $remainingAttempts,
    'attempts_left'      => $remainingAttempts,
]);

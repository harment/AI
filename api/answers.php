<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

$studentId  = (int)$_SESSION['student_id'];
$db         = getDB();

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
$body       = json_decode(file_get_contents('php://input'), true) ?? [];

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
$lessonId   = (int)($body['lesson_id']   ?? 0);
$questionId = (int)($body['question_id'] ?? 0);
$isCorrect  = isset($body['is_correct']) ? (int)(bool)$body['is_correct'] : 0;
$attempts   = max(1, (int)($body['attempts_count'] ?? 1));

if (!$lessonId || !$questionId) {
    jsonResponse(['error' => 'lesson_id and question_id required'], 400);
}

// Always insert a new row per answer attempt to preserve full history for analysis
if (dbTableExists($db, 'question_attempts')) {
    $db->prepare(
        "INSERT INTO question_attempts (student_id, lesson_id, question_id, is_correct, attempts_count) VALUES (?,?,?,?,?)"
    )->execute([$studentId, $lessonId, $questionId, $isCorrect, $attempts]);
}

// توافق رجعي: بعض القواعد القديمة تعتمد على question_answers
if (dbTableExists($db, 'question_answers')) {
    $dataMap = [
        'student_id'     => $studentId,
        'lesson_id'      => $lessonId,
        'question_id'    => $questionId,
        'is_correct'     => $isCorrect,
        'attempts_count' => $attempts,
        'created_at'     => date('Y-m-d H:i:s'),
    ];
    $insertCols = [];
    $insertVals = [];
    foreach ($dataMap as $col => $val) {
        if (dbTableHasColumn($db, 'question_answers', $col)) {
            $insertCols[] = $col;
            $insertVals[] = $val;
        }
    }
    if (!empty($insertCols)) {
        $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
        $db->prepare("INSERT INTO question_answers (" . implode(',', $insertCols) . ") VALUES ($placeholders)")
            ->execute($insertVals);
    }
}

if (dbTableExists($db, 'question_stats')) {
    $db->prepare("
        INSERT INTO question_stats (question_id, total_answers, correct_count)
        VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE
            total_answers = total_answers + 1,
            correct_count = correct_count + VALUES(correct_count)
    ")->execute([$questionId, $isCorrect ? 1 : 0]);
}

jsonResponse(['success' => true]);

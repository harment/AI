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

function dbTableColumns(PDO $db, string $table): array
{
    $stmt = $db->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([$table]);
    return $stmt->fetchAll() ?: [];
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
    $now = date('Y-m-d H:i:s');
    $valueMap = [
        'student_id'      => $studentId,
        'lesson_id'       => $lessonId,
        'question_id'     => $questionId,
        'is_correct'      => $isCorrect,
        'correct'         => $isCorrect,
        'is_right'        => $isCorrect,
        'attempts_count'  => $attempts,
        'attempt_count'   => $attempts,
        'tries'           => $attempts,
        'created_at'      => $now,
        'updated_at'      => $now,
        'selected_option' => '',
        'student_answer'  => '',
        'answer'          => '',
    ];

    $insertCols = [];
    $insertVals = [];
    foreach (dbTableColumns($db, 'question_answers') as $colMeta) {
        $col = (string)$colMeta['COLUMN_NAME'];
        $type = strtolower((string)$colMeta['DATA_TYPE']);
        $nullable = ((string)$colMeta['IS_NULLABLE']) === 'YES';
        $hasDefault = $colMeta['COLUMN_DEFAULT'] !== null;
        $extra = strtolower((string)$colMeta['EXTRA']);

        if (str_contains($extra, 'auto_increment')) {
            continue;
        }

        if (array_key_exists($col, $valueMap)) {
            $insertCols[] = $col;
            $insertVals[] = $valueMap[$col];
            continue;
        }

        if ($nullable || $hasDefault) {
            continue;
        }

        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'], true)) {
            $fallback = 0;
        } elseif (in_array($type, ['date', 'datetime', 'timestamp'], true)) {
            $fallback = $now;
        } elseif ($type === 'json') {
            $fallback = '{}';
        } else {
            $fallback = '';
        }

        $insertCols[] = $col;
        $insertVals[] = $fallback;
    }

    if (!empty($insertCols)) {
        try {
            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $db->prepare("INSERT INTO question_answers (" . implode(',', $insertCols) . ") VALUES ($placeholders)")
                ->execute($insertVals);
        } catch (Throwable $e) {
            // لا نُفشل الطلب الرئيسي إذا كان مخطط question_answers مخصصاً بشكل مختلف
        }
    }
}

if (dbTableExists($db, 'question_stats')) {
    if (dbTableHasColumn($db, 'question_stats', 'wrong_count')) {
        $db->prepare("
            INSERT INTO question_stats (question_id, total_answers, correct_count, wrong_count)
            VALUES (?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_answers = total_answers + 1,
                correct_count = correct_count + VALUES(correct_count),
                wrong_count   = wrong_count + VALUES(wrong_count)
        ")->execute([$questionId, $isCorrect ? 1 : 0, $isCorrect ? 0 : 1]);
    } else {
        $db->prepare("
            INSERT INTO question_stats (question_id, total_answers, correct_count)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                total_answers = total_answers + 1,
                correct_count = correct_count + VALUES(correct_count)
        ")->execute([$questionId, $isCorrect ? 1 : 0]);
    }
}

jsonResponse(['success' => true]);

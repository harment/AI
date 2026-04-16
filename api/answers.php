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
$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId   = (int)($body['lesson_id']   ?? 0);
$questionId = (int)($body['question_id'] ?? 0);
$isCorrect  = isset($body['is_correct']) ? (int)(bool)$body['is_correct'] : 0;
$attempts   = max(1, (int)($body['attempts_count'] ?? 1));

if (!$lessonId || !$questionId) {
    jsonResponse(['error' => 'lesson_id and question_id required'], 400);
}

// Upsert: one row per student+question per lesson (keep first attempt, update if re-answering)
$existing = $db->prepare(
    "SELECT id FROM question_attempts WHERE student_id=? AND lesson_id=? AND question_id=?"
);
$existing->execute([$studentId, $lessonId, $questionId]);
if ($existing->fetch()) {
    $db->prepare(
        "UPDATE question_attempts SET is_correct=?, attempts_count=? WHERE student_id=? AND lesson_id=? AND question_id=?"
    )->execute([$isCorrect, $attempts, $studentId, $lessonId, $questionId]);
} else {
    $db->prepare(
        "INSERT INTO question_attempts (student_id, lesson_id, question_id, is_correct, attempts_count) VALUES (?,?,?,?,?)"
    )->execute([$studentId, $lessonId, $questionId, $isCorrect, $attempts]);
}

jsonResponse(['success' => true]);

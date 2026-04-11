<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    http_response_code(401); exit;
}

$studentId = (int)$_SESSION['student_id'];
$body      = json_decode(file_get_contents('php://input'), true);
$lessonId  = (int)($body['lesson_id'] ?? 0);
$duration  = (int)($body['duration'] ?? 0);

if ($lessonId && $duration > 0) {
    $db = getDB();
    $db->prepare("INSERT INTO activity_log (student_id, lesson_id, action, duration_seconds) VALUES (?,?,'time_spent',?)")
       ->execute([$studentId, $lessonId, $duration]);
}

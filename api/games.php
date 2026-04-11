<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$studentId = (int)$_SESSION['student_id'];
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true);
$lessonId  = (int)($body['lesson_id'] ?? 0);
$points    = (int)($body['points'] ?? 0);
$scholarId = $body['scholar_id'] ? (int)$body['scholar_id'] : null;
$completed = (int)($body['completed'] ?? 0);

if (!$lessonId) {
    jsonResponse(['error' => 'lesson_id required'], 400);
}

// Check lesson exists and open
$lesson = $db->prepare("SELECT id FROM lessons WHERE id=? AND is_open=1");
$lesson->execute([$lessonId]);
if (!$lesson->fetch()) {
    jsonResponse(['error' => 'Lesson not found or closed'], 404);
}

// Increment attempts
$existing = $db->prepare("SELECT id, attempts FROM student_games WHERE student_id=? AND lesson_id=? ORDER BY id DESC LIMIT 1");
$existing->execute([$studentId, $lessonId]);
$existing = $existing->fetch();

if ($existing && !$completed) {
    // Update attempts
    $db->prepare("UPDATE student_games SET attempts=attempts+1 WHERE id=?")->execute([$existing['id']]);
} else {
    // Insert new record
    $db->prepare("INSERT INTO student_games (student_id, lesson_id, points_earned, scholar_id, completed) VALUES (?,?,?,?,?)")
       ->execute([$studentId, $lessonId, $points, $scholarId, $completed]);
}

// Update student points
if ($points > 0 && $completed) {
    $db->prepare("UPDATE students SET points=points+? WHERE id=?")->execute([$points, $studentId]);
}

// Add discovered scholar
if ($scholarId && $completed) {
    try {
        $db->prepare("INSERT IGNORE INTO student_scholars (student_id, scholar_id) VALUES (?,?)")->execute([$studentId, $scholarId]);
    } catch (PDOException $e) {}
    logActivity($studentId, $lessonId, 'game_win', "نقاط: $points", 0);
} else {
    logActivity($studentId, $lessonId, 'game_play', '', 0);
}

jsonResponse(['success' => true, 'points_added' => $points]);

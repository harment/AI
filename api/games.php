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

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId  = (int)($body['lesson_id'] ?? 0);
$points    = (int)($body['points'] ?? 0);
$scholarId = !empty($body['scholar_id']) ? (int)$body['scholar_id'] : null;
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

// One row per student+lesson: upsert approach
$existing = $db->prepare("SELECT id, completed, attempts FROM student_games WHERE student_id=? AND lesson_id=? ORDER BY id DESC LIMIT 1");
$existing->execute([$studentId, $lessonId]);
$existing = $existing->fetch();

$pointsAdded = 0;
if (!$existing) {
    // First play – insert new record
    $db->prepare("INSERT INTO student_games (student_id, lesson_id, points_earned, scholar_id, completed) VALUES (?,?,?,?,?)")
       ->execute([$studentId, $lessonId, $points, $scholarId, $completed]);
    if ($completed && $points > 0) {
        $pointsAdded = $points;
    }
} elseif ($completed && !$existing['completed']) {
    // First win – upgrade existing record to completed and award points
    $db->prepare("UPDATE student_games SET completed=1, points_earned=?, scholar_id=?, attempts=attempts+1 WHERE id=?")
       ->execute([$points, $scholarId, $existing['id']]);
    $pointsAdded = $points;
} else {
    // Replay (already completed or another loss) – just increment attempts
    $db->prepare("UPDATE student_games SET attempts=attempts+1 WHERE id=?")->execute([$existing['id']]);
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

jsonResponse(['success' => true, 'points_added' => $pointsAdded]);

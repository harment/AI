<?php
// توافق رجعي: بعض النسخ القديمة من الواجهة تستدعي هذا المسار
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (empty($_SESSION['student_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// بعض النسخ القديمة تنفذ فحص محاولات يومي عبر GET قبل الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'success'            => true,
        'can_play'           => true,
        'max_attempts'       => 9999,
        'remaining_attempts' => 9999,
        'attempts_left'      => 9999,
        'retry_after'        => null,
        'retry_after_text'   => '',
    ]);
}

// POST (حفظ نتيجة اللعبة) يبقى على المسار الحالي
require __DIR__ . '/games.php';

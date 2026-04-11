<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    header('Location: /'); exit;
}

if ($action === 'logout_teacher') {
    session_destroy();
    header('Location: /teacher/login.php'); exit;
}

jsonResponse(['error' => 'invalid action'], 400);

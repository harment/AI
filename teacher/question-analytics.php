<?php
// Redirect legacy URL to the canonical question analysis page
$query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /teacher/question_analysis.php' . $query, true, 301);
exit;

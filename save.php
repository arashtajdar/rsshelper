<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$date = $_POST['date'] ?? date('Y-m-d');
$news_ids = $_POST['news_ids'] ?? [];

if (!empty($news_ids) && is_array($news_ids)) {
    // Validate IDs are integers
    $valid_ids = array_filter($news_ids, function($id) {
        return filter_var($id, FILTER_VALIDATE_INT) !== false;
    });

    if (!empty($valid_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($valid_ids)), ',');

        $sql = "UPDATE news SET status = 1 WHERE id IN ($placeholders)";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($valid_ids));
    }
}

header("Location: index.php?date=" . urlencode($date));
die();

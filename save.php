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

        $user_id = $_SESSION['user_id'];
        foreach ($valid_ids as $news_id) {
            $stmt = $db->prepare("
                INSERT INTO user_news_status (user_id, news_id, status) 
                VALUES (:user_id, :news_id, 1) 
                ON DUPLICATE KEY UPDATE status = VALUES(status)
            ");
            $stmt->execute([':user_id' => $user_id, ':news_id' => $news_id]);
        }
    }
}

header("Location: index.php?date=" . urlencode($date));
die();

<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Invalid request method");
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if ($id && in_array($action, ['accept', 'reject', 'reset'])) {
    $status = 0;
    if ($action === 'accept') $status = 1;
    if ($action === 'reject') $status = 2;
    
    $user_id = $_SESSION['user_id'];
    
    // Using MySQL upsert (INSERT ... ON DUPLICATE KEY UPDATE)
    $stmt = $db->prepare("
        INSERT INTO user_news_status (user_id, news_id, status) 
        VALUES (:user_id, :news_id, :status) 
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ");
    $stmt->execute([':user_id' => $user_id, ':news_id' => $id, ':status' => $status]);
    
    // Log action history
    $log_stmt = $db->prepare("INSERT INTO action_history (user_id, news_id, action_type) VALUES (:user_id, :news_id, :action_type)");
    $log_stmt->execute([':user_id' => $user_id, ':news_id' => $id, ':action_type' => $action]);

    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
}

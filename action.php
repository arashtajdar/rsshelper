<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Invalid request method");
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if ($id && in_array($action, ['accept', 'reject'])) {
    $status = $action === 'accept' ? 1 : 2;
    $stmt = $db->prepare("UPDATE news SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
}

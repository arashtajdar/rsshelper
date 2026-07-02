<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("INSERT INTO action_history (user_id, action_type) VALUES (:user_id, 'logout')");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
    } catch (PDOException $e) {
        logMessage("Failed to log logout action: " . $e->getMessage());
    }
}

session_destroy();
header("Location: auth.php");
die();
?>

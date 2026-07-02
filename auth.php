<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $stmt = $db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $user = false;
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        try {
            $stmt = $db->prepare("INSERT INTO action_history (user_id, action_type) VALUES (:user_id, 'login')");
            $stmt->execute([':user_id' => $user['id']]);
        } catch (PDOException $e) {
            logMessage("Failed to log login action for user " . $user['id'] . ": " . $e->getMessage());
        }

        header("Location: index.php");
        die();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f4f4f9; }
        .login-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 90%; max-width: 400px; box-sizing: border-box; }
        input[type="text"], input[type="password"] { padding: 10px; width: 100%; box-sizing: border-box; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        button:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="auth.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <br>
            <button type="submit">Login</button>
        </form>
    </div>
    <?php require 'footer.php'; ?>
</body>
</html>
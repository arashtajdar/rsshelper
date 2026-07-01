<?php
require_once 'config.php';
requireAuth();
requireAdmin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $new_role = $_POST['new_role'] ?? 'user';
    
    if ($new_username && $new_password) {
        try {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)");
            $stmt->execute([
                ':username' => $new_username,
                ':password_hash' => $hash,
                ':role' => $new_role
            ]);
            $message = "User '$new_username' created successfully.";
        } catch (PDOException $e) {
            $message = "Error creating user: " . $e->getMessage();
        }
    } else {
        $message = "Username and password are required.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_user_id = filter_input(INPUT_POST, 'delete_user_id', FILTER_VALIDATE_INT);
    if ($del_user_id && $del_user_id !== $_SESSION['user_id']) {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $del_user_id]);
            $message = "User deleted successfully.";
        } catch (PDOException $e) {
            $message = "Error deleting user: " . $e->getMessage();
        }
    } elseif ($del_user_id === $_SESSION['user_id']) {
        $message = "You cannot delete yourself.";
    }
}

// Fetch Users for Dropdown and Management
$users_stmt = $db->query("SELECT id, username, role FROM users ORDER BY username ASC");
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Action History
$filter_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

$history_query = "
    SELECT h.created_at, u.username, h.action_type, n.title, n.link 
    FROM action_history h
    LEFT JOIN users u ON h.user_id = u.id
    LEFT JOIN news n ON h.news_id = n.id
";

$params = [];
if ($filter_user_id) {
    $history_query .= " WHERE h.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}

$history_query .= " ORDER BY h.created_at DESC LIMIT 100";

$hist_stmt = $db->prepare($history_query);
$hist_stmt->execute($params);
$history_logs = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; color: #007bff; text-decoration: none; }
        .message { padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px; }
        button { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="nav" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="index.php">Dashboard</a> | <strong>Admin Dashboard</strong>
        </div>
        <div>
            <?php if (isset($_SESSION['username'])): ?>
                Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> | 
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <h2>Admin Dashboard</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <p>Welcome, Administrator.</p>

    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3>Manage Users</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f1f1f1; text-align: left;">
                    <th style="padding: 10px; border-bottom: 2px solid #ccc;">ID</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ccc;">Username</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ccc;">Role</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ccc;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_users as $u): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><?= htmlspecialchars($u['id']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($u['username']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($u['role']) ?></td>
                        <td style="padding: 10px;">
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="delete_user" value="1" style="background-color: #dc3545; padding: 5px 10px; font-size: 12px;">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4>Create New User</h4>
        <form method="POST" action="admin.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="new_username" placeholder="Username" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="password" name="new_password" placeholder="Password" required style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <select name="new_role" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" name="create_user" value="1">Create User</button>
        </form>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3>User Action History</h3>
        
        <form method="GET" action="admin.php" style="margin-bottom: 20px;">
            <label for="user_id">Filter by User:</label>
            <select name="user_id" id="user_id" style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                <option value="">All Users</option>
                <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filter_user_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="padding: 5px 10px; font-size: 14px;">Filter</button>
        </form>

        <?php if (count($history_logs) > 0): ?>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background-color: #f1f1f1; text-align: left;">
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Date/Time</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Username</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Action</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">News Title</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_logs as $log): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; color: #555; white-space: nowrap;"><?= htmlspecialchars($log['created_at']) ?></td>
                            <td style="padding: 10px; font-weight: bold;"><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                            <td style="padding: 10px;">
                                <?php
                                    $action_color = '#6c757d'; // Default gray for reset
                                    if ($log['action_type'] === 'accept') $action_color = '#28a745'; // Green
                                    elseif ($log['action_type'] === 'reject') $action_color = '#dc3545'; // Red
                                ?>
                                <span style="color: <?= $action_color ?>; font-weight: bold; text-transform: capitalize;"><?= htmlspecialchars($log['action_type']) ?></span>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ($log['link']): ?>
                                    <a href="<?= htmlspecialchars($log['link']) ?>" target="_blank" style="color: #007bff; text-decoration: none;"><?= htmlspecialchars($log['title']) ?></a>
                                <?php else: ?>
                                    <span style="color: #999;">Deleted/Unknown</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No action history found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
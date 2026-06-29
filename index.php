<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');

// Fetch pending news for the selected date
$stmt = $db->prepare("SELECT * FROM news WHERE status = 0 AND created_date = :date ORDER BY id DESC");
$stmt->execute([':date' => $selected_date]);
$news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>News Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .controls { display: flex; gap: 10px; align-items: center; }
        .news-list { list-style: none; padding: 0; }
        .news-item { background: white; padding: 10px; margin-bottom: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .news-item label { display: block; cursor: pointer; }
        .news-item input[type="checkbox"] { margin-right: 10px; }
        .news-item a { color: #007bff; text-decoration: none; font-weight: bold; }
        .news-item a:hover { text-decoration: underline; }
        button, .btn { padding: 8px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; }
        button:hover, .btn:hover { background: #0056b3; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="nav">
        <strong>Dashboard</strong> | <a href="export.php">Export / Archive</a>
    </div>

    <div class="header">
        <h2>Pending News for <?= htmlspecialchars($selected_date) ?></h2>

        <div class="controls">
            <form method="GET" action="index.php">
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                <button type="submit">View Date</button>
            </form>
            <form method="POST" action="fetch.php">
                <button type="submit" style="background-color: #28a745;">Fetch Latest News</button>
            </form>
        </div>
    </div>

    <?php if (count($news_items) > 0): ?>
        <form method="POST" action="save.php">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
            <ul class="news-list">
                <?php foreach ($news_items as $item): ?>
                    <li class="news-item">
                        <label>
                            <input type="checkbox" name="news_ids[]" value="<?= $item['id'] ?>">
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"><?= htmlspecialchars($item['title']) ?></a>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="submit">Submit Selected Headlines</button>
        </form>
    <?php else: ?>
        <p>No pending news for this date.</p>
    <?php endif; ?>

</body>
</html>

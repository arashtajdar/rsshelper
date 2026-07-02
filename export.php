<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$action = $_POST['action'] ?? '';

// Handle clear action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'clear') {
    $clear_date = $_POST['clear_date'] ?? $selected_date;
    // Instead of deleting the news item completely, we should reset the user's status or remove it
    $stmt = $db->prepare("
        DELETE FROM user_news_status 
        WHERE user_id = :user_id 
        AND status = 1 
        AND news_id IN (SELECT id FROM news WHERE DATE(published) = :date)
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id'], ':date' => $clear_date]);
    header("Location: export.php?date=" . urlencode($clear_date));
    die();
}

// Fetch selected news for the given date
$stmt = $db->prepare("
    SELECT news.* 
    FROM news 
    INNER JOIN user_news_status ON news.id = user_news_status.news_id 
    WHERE user_news_status.user_id = :user_id 
      AND user_news_status.status = 1 
      AND DATE(news.published) = :date 
    ORDER BY news.published DESC
");
$stmt->execute([':user_id' => $_SESSION['user_id'], ':date' => $selected_date]);
$news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare text for copy
$export_text = "";
foreach ($news_items as $item) {
    $authorStr = $item['author'] ? " (by " . $item['author'] . ")" : "";
    $export_text .= "- " . $item['title'] . $authorStr . "\n" .
                    "  " . ($item['published'] ? $item['published'] . " - " : "") . $item['link'] . "\n" .
                    ($item['description'] ? "  " . $item['description'] . "\n" : "") . "\n";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export News</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .controls { display: flex; gap: 10px; align-items: center; }
        .news-list { list-style: none; padding: 0; }
        .news-item { background: white; padding: 10px; margin-bottom: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .news-item a { color: #007bff; text-decoration: none; font-weight: bold; }
        .news-item a:hover { text-decoration: underline; }
        button, .btn { padding: 8px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; }
        button:hover, .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        textarea { width: 100%; height: 200px; padding: 10px; margin-bottom: 20px; font-family: monospace; box-sizing: border-box; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; color: #007bff; text-decoration: none; }
        
        /* Mobile Responsive */
        @media (max-width: 600px) {
            body { margin: 10px; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .controls { flex-direction: column; align-items: stretch; width: 100%; }
            .controls form { display: flex; flex-direction: column; gap: 10px; width: 100%; }
            .controls input[type="date"], .controls button { width: 100%; box-sizing: border-box; }
        }
    </style>
</head>
<body>
    <div class="nav" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="index.php">Dashboard</a> | <strong>Export / Archive</strong>
        </div>
        <div>
            <?php if (isset($_SESSION['username'])): ?>
                Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> | 
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="header">
        <h2>Exported News for <?= htmlspecialchars($selected_date) ?></h2>

        <div class="controls">
            <form method="GET" action="export.php">
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                <button type="submit">View Date</button>
            </form>

            <?php if (count($news_items) > 0): ?>
                <form method="POST" action="export.php" onsubmit="return confirm('Are you sure you want to clear all selected records for this date?');">
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="clear_date" value="<?= htmlspecialchars($selected_date) ?>">
                    <button type="submit" class="btn-danger">Clear Selected Records</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($news_items) > 0): ?>
        <h3>Copy to Clipboard</h3>
        <textarea id="exportText" readonly><?= htmlspecialchars($export_text) ?></textarea>
        <button onclick="copyToClipboard()" style="margin-bottom: 20px;">Copy Text</button>

        <h3>Selected Headlines</h3>
        <ul class="news-list">
            <?php foreach ($news_items as $item): ?>
                <li class="news-item">
                    <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"><?= htmlspecialchars($item['title']) ?></a>
                    <?php if ($item['author']): ?>
                        <span style="color: #666; font-size: 0.9em;"> by <?= htmlspecialchars($item['author']) ?></span>
                    <?php endif; ?>
                    <?php if ($item['published']): ?>
                        <div style="color: #888; font-size: 0.8em; margin-top: 3px;"><?= htmlspecialchars(date('M j, Y, g:i a', strtotime($item['published']))) ?></div>
                    <?php endif; ?>
                    <?php if ($item['description']): ?>
                        <div style="font-size: 0.85em; color: #555; margin-top: 5px; line-height: 1.3;"><?= htmlspecialchars($item['description']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <script>
            function copyToClipboard() {
                var copyText = document.getElementById("exportText");
                copyText.select();
                copyText.setSelectionRange(0, 99999); // For mobile devices
                document.execCommand("copy");
                alert("Copied the text!");
            }
        </script>
    <?php else: ?>
        <p>No selected news for this date.</p>
    <?php endif; ?>

    <?php require 'footer.php'; ?>
</body>
</html>

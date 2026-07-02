<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$action = $_POST['action'] ?? '';


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
        .btn-reject { background: #dc3545; padding: 4px 8px; font-size: 12px; }
        .btn-reject:hover { background: #c82333; }
        .btn-reset { background: #6c757d; color: white; border: none; padding: 4px 8px; font-size: 12px; border-radius: 4px; cursor: pointer; }
        .btn-reset:hover { background: #5a6268; }
        textarea { width: 100%; height: 200px; padding: 10px; margin-bottom: 20px; font-family: monospace; box-sizing: border-box; }
        
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            font-size: 15px;
        }
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .nav-links a,
        .nav-links span {
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .nav-links a:hover {
            color: #007bff;
            background-color: #f0f4f8;
        }
        .nav-links .active-link {
            color: #007bff;
            background-color: #e6f0fa;
            font-weight: 700;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            color: #6c757d;
        }
        .user-info strong {
            color: #212529;
            font-weight: 600;
        }
        .logout-btn {
            background-color: #fff;
            color: #dc3545;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none !important;
            font-weight: 600;
            transition: all 0.2s;
            border: 1px solid #dc3545;
        }
        .logout-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        .source-badge { display: inline-block; padding: 2px 6px; background: #eee; color: #555; border-radius: 3px; font-size: 12px; font-weight: bold; }
        
        /* Mobile Responsive */
        @media (max-width: 600px) {
            body { margin: 10px; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .controls { flex-direction: column; align-items: stretch; width: 100%; }
            .controls form { display: flex; flex-direction: column; gap: 10px; width: 100%; }
            .controls input[type="date"], .controls button { width: 100%; box-sizing: border-box; }
            .top-navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            .nav-links,
            .user-info {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="top-navbar">
        <div class="nav-links">
            <img src="assets/logo.png" alt="Logo" style="height: 36px; margin-right: 10px; object-fit: contain;">
            <a href="index.php">Dashboard</a>
            <span class="active-link">Export / Archive</span>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin.php">Admin Dashboard</a>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <?php if (isset($_SESSION['username'])): ?>
                <span>Logged in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="header">
        <h2>Exported News for <?= htmlspecialchars($selected_date) ?></h2>

        <div class="controls">
            <form method="GET" action="export.php">
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                <button type="submit">View Date</button>
            </form>
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
                    <div style="display: flex; align-items: flex-start;">
                        <div style="flex: 0 0 60px; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                            <?php $logo = $news_sources[$item['source_id']]['logo'] ?? null; ?>
                            <?php if ($logo): ?>
                                <img src="assets/logos/<?= htmlspecialchars($logo) ?>"
                                    alt="<?= htmlspecialchars($item['source'] ?? 'Unknown') ?>"
                                    style="max-width: 60px; max-height: 60px; object-fit: contain;">
                            <?php else: ?>
                                <?php $badge_color = $source_colors[$item['source_id']] ?? '#eee'; ?>
                                <span class="source-badge"
                                    style="background-color: <?= $badge_color ?>; border: 1px solid rgba(0,0,0,0.1); vertical-align: middle;"><?= htmlspecialchars($item['source'] ?? 'Unknown') ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
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
                        <div class="news-actions" style="white-space: nowrap; margin-left: 10px; display: flex; flex-direction: column; gap: 5px; justify-content: center;">
                            <button type="button" id="btn-reject-<?= $item['id'] ?>" onclick="triage(<?= $item['id'] ?>, 'reject')" class="btn-reject">Reject</button>
                            <button type="button" id="btn-reset-<?= $item['id'] ?>" onclick="triage(<?= $item['id'] ?>, 'reset')" class="btn-reset">Undo</button>
                        </div>
                    </div>
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

            function triage(id, action) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', action);

                fetch('action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error updating item.');
                    }
                })
                .catch(err => {
                    alert('Network error.');
                });
            }
        </script>
    <?php else: ?>
        <p>No selected news for this date.</p>
    <?php endif; ?>

    <?php require 'footer.php'; ?>
</body>
</html>

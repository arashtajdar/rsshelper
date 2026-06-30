<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_source = $_GET['source'] ?? '';
$selected_search = $_GET['search'] ?? '';
$selected_status = $_GET['status'] ?? '0';

// Build query with optional source filter
$query = "SELECT * FROM news WHERE created_date = :date";
$params = [':date' => $selected_date];

if ($selected_status !== 'all') {
    $query .= " AND status = :status";
    $params[':status'] = (int)$selected_status;
}

if ($selected_source !== '') {
    $query .= " AND source = :source";
    $params[':source'] = $selected_source;
}

if ($selected_search !== '') {
    $query .= " AND title LIKE :search";
    $params[':search'] = '%' . $selected_search . '%';
}

$query .= " ORDER BY id DESC";

// Fetch pending news for the selected date
$stmt = $db->prepare($query);
$stmt->execute($params);
$news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available sources for the dropdown
$src_stmt = $db->prepare("SELECT DISTINCT source FROM news WHERE status = 0 AND created_date = :date AND source IS NOT NULL ORDER BY source ASC");
$src_stmt->execute([':date' => $selected_date]);
$available_sources = $src_stmt->fetchAll(PDO::FETCH_COLUMN);

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
        .source-badge { display: inline-block; padding: 2px 6px; background: #eee; color: #555; border-radius: 3px; font-size: 12px; margin-right: 8px; font-weight: bold; }
        button, .btn { padding: 8px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; }
        button:hover, .btn:hover { background: #0056b3; }
        .btn-accept { background: #28a745; padding: 4px 8px; font-size: 12px; }
        .btn-accept:hover { background: #218838; }
        .btn-reject { background: #dc3545; padding: 4px 8px; font-size: 12px; }
        .btn-reject:hover { background: #c82333; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="nav">
        <strong>Dashboard</strong> | <a href="export.php">Export / Archive</a>
    </div>

<?php 
    $today_str = date('Y-m-d');
    $yesterday_str = date('Y-m-d', strtotime('-1 day'));
    $can_fetch = ($selected_date === $today_str || $selected_date === $yesterday_str);
    ?>
    <div class="header">
        <h2>Pending News for <?= htmlspecialchars($selected_date) ?></h2>
        
        <?php if ($can_fetch): ?>
        <form method="POST" action="fetch.php">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
            <button type="submit" style="background-color: #28a745;">Fetch Latest News</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="filter-bar" style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="GET" action="index.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
            <select name="status">
                <option value="0" <?= $selected_status === '0' ? 'selected' : '' ?>>Pending</option>
                <option value="1" <?= $selected_status === '1' ? 'selected' : '' ?>>Accepted</option>
                <option value="2" <?= $selected_status === '2' ? 'selected' : '' ?>>Rejected</option>
                <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
            </select>
            <select name="source">
                <option value="">All Sources</option>
                <?php foreach ($available_sources as $src): ?>
                    <option value="<?= htmlspecialchars($src) ?>" <?= $src === $selected_source ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" value="<?= htmlspecialchars($selected_search) ?>" placeholder="Search titles..." style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; flex-grow: 1; min-width: 200px;">
            <button type="submit">Apply Filters</button>
        </form>
    </div>

    <?php if (count($news_items) > 0): ?>
        <ul class="news-list">
            <?php foreach ($news_items as $item): 
                $bg_color = 'white';
                if ($item['status'] == 1) $bg_color = '#d4edda';
                if ($item['status'] == 2) $bg_color = '#f8d7da';
            ?>
                <li class="news-item" id="item-<?= $item['id'] ?>" style="background-color: <?= $bg_color ?>; transition: background-color 0.3s ease;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="source-badge"><?= htmlspecialchars($item['source'] ?? 'Unknown') ?></span>
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"><?= htmlspecialchars($item['title']) ?></a>
                        </div>
                        <div style="white-space: nowrap; margin-left: 10px;">
                            <button type="button" onclick="triage(<?= $item['id'] ?>, 'accept', this)" class="btn-accept">Accept</button>
                            <button type="button" onclick="triage(<?= $item['id'] ?>, 'reject', this)" class="btn-reject">Reject</button>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <script>
            function triage(id, action, btn) {
                const originalText = btn.innerText;
                btn.innerText = '...';
                btn.disabled = true;

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
                        const li = document.getElementById('item-' + id);
                        if (li) {
                            if (action === 'accept') {
                                li.style.backgroundColor = '#d4edda';
                            } else {
                                li.style.backgroundColor = '#f8d7da';
                            }
                        }
                    } else {
                        alert('Error updating item.');
                    }
                    btn.innerText = originalText;
                    btn.disabled = false;
                })
                .catch(err => {
                    alert('Network error.');
                    btn.innerText = originalText;
                    btn.disabled = false;
                });
            }
        </script>
    <?php else: ?>
        <p>No news found for these filters.</p>
    <?php endif; ?>

    <div style="margin-top: 40px; padding: 20px 0; border-top: 1px solid #ccc; text-align: center; color: #777; font-size: 12px;">
        &copy; <?= date('Y') ?> RSS Helper. All rights reserved.
    </div>
</body>
</html>

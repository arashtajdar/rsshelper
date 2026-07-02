<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_source = $_GET['source'] ?? '';
$selected_search = $_GET['search'] ?? '';
$selected_status = $_GET['status'] ?? '0';

// Build query with optional source filter
$current_user_id = $_SESSION['user_id'];

$query = "SELECT news.*, COALESCE(user_news_status.status, 0) AS status 
          FROM news 
          LEFT JOIN user_news_status ON news.id = user_news_status.news_id AND user_news_status.user_id = :current_user_id 
          WHERE news.created_date = :date";

$params = [
    ':date' => $selected_date,
    ':current_user_id' => $current_user_id
];

if ($selected_status !== 'all') {
    $query .= " AND COALESCE(user_news_status.status, 0) = :status";
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
$src_stmt = $db->prepare("
    SELECT DISTINCT news.source 
    FROM news 
    LEFT JOIN user_news_status ON news.id = user_news_status.news_id AND user_news_status.user_id = :current_user_id 
    WHERE COALESCE(user_news_status.status, 0) = 0 
      AND news.created_date = :date 
      AND news.source IS NOT NULL 
    ORDER BY news.source ASC
");
$src_stmt->execute([':date' => $selected_date, ':current_user_id' => $current_user_id]);
$available_sources = $src_stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .btn-reset { background: #6c757d; color: white; border: none; padding: 4px 8px; font-size: 12px; border-radius: 4px; cursor: pointer; }
        .btn-reset:hover { background: #5a6268; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; color: #007bff; text-decoration: none; }
        
        /* Mobile Responsive */
        @media (max-width: 600px) {
            body { margin: 10px; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .filter-bar form { flex-direction: column; align-items: stretch; }
            .filter-bar input[type="text"], .filter-bar input[type="date"], .filter-bar select, .filter-bar button { width: 100%; box-sizing: border-box; }
            .news-content { flex-direction: column; align-items: flex-start; gap: 10px; }
            .news-actions { align-self: stretch; display: flex; justify-content: flex-end; gap: 10px; }
            .news-actions button { flex: 1; padding: 10px; font-size: 14px; }
            .source-badge { display: block; margin-bottom: 5px; width: fit-content; }
        }
    </style>
</head>
<body>
    <div class="nav" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>Dashboard</strong> | <a href="export.php">Export / Archive</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                | <a href="admin.php">Admin Dashboard</a>
            <?php endif; ?>
        </div>
        <div>
            <?php if (isset($_SESSION['username'])): ?>
                Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> | 
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
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

    <?php if (isset($_SESSION['admin_fetch_log']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <div style="background: #e8f4f8; padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #bce8f1; color: #31708f;">
        <h3 style="margin-top: 0;">Fetch Log Summary</h3>
        <p><strong>Total Successfully Inserted:</strong> <?= $_SESSION['admin_fetch_log']['total_success'] ?> <br>
           <strong>Total Feed Errors:</strong> <?= $_SESSION['admin_fetch_log']['total_errors'] ?></p>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; background: white; text-align: left;">
            <thead>
                <tr style="background: #d9edf7;">
                    <th style="padding: 8px; border: 1px solid #bce8f1;">Agency</th>
                    <th style="padding: 8px; border: 1px solid #bce8f1;">New Items Fetched</th>
                    <th style="padding: 8px; border: 1px solid #bce8f1;">Error (if any)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['admin_fetch_log']['details'] as $agency => $stats): ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #bce8f1; font-weight: bold;"><?= htmlspecialchars($agency) ?></td>
                    <td style="padding: 8px; border: 1px solid #bce8f1;"><?= $stats['fetched'] ?></td>
                    <td style="padding: 8px; border: 1px solid #bce8f1; color: <?= $stats['error'] ? '#a94442' : '#3c763d' ?>;">
                        <?= $stats['error'] ? htmlspecialchars($stats['error']) : 'None' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php unset($_SESSION['admin_fetch_log']); ?>
    <?php endif; ?>

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
        <?php $news_count = count($news_items); ?>
        <ul class="news-list">
            <?php foreach ($news_items as $item): 
                $bg_color = 'white';
                if ($item['status'] == 1) $bg_color = '#d4edda';
                if ($item['status'] == 2) $bg_color = '#f8d7da';
            ?>
                <li class="news-item" id="item-<?= $item['id'] ?>" style="background-color: <?= $bg_color ?>; transition: background-color 0.3s ease;">
                    <div class="news-content" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <?php $badge_color = $source_colors[$item['source']] ?? '#eee'; ?>
                            <span class="source-badge" style="background-color: <?= $badge_color ?>; border: 1px solid rgba(0,0,0,0.1);"><?= htmlspecialchars($item['source'] ?? 'Unknown') ?></span>
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" style="line-height: 1.4; display: inline-block;"><?= $news_count-- ?>. <?= htmlspecialchars($item['title']) ?></a>
                        </div>
                        <div class="news-actions" style="white-space: nowrap; margin-left: 10px;">
                            <button type="button" id="btn-accept-<?= $item['id'] ?>" onclick="triage(<?= $item['id'] ?>, 'accept')" class="btn-accept" style="<?= $item['status'] == 1 ? 'display: none;' : '' ?>">Accept</button>
                            <button type="button" id="btn-reject-<?= $item['id'] ?>" onclick="triage(<?= $item['id'] ?>, 'reject')" class="btn-reject" style="<?= $item['status'] == 2 ? 'display: none;' : '' ?>">Reject</button>
                            <button type="button" id="btn-reset-<?= $item['id'] ?>" onclick="triage(<?= $item['id'] ?>, 'reset')" class="btn-reset" style="<?= $item['status'] == 0 ? 'display: none;' : '' ?>">Undo</button>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <script>
            function triage(id, action) {
                const btnAccept = document.getElementById('btn-accept-' + id);
                const btnReject = document.getElementById('btn-reject-' + id);
                const btnReset = document.getElementById('btn-reset-' + id);
                
                let targetBtn;
                if (action === 'accept') targetBtn = btnAccept;
                else if (action === 'reject') targetBtn = btnReject;
                else targetBtn = btnReset;
                
                const originalText = targetBtn.innerText;
                targetBtn.innerText = '...';

                // Disable all buttons while loading
                btnAccept.disabled = true;
                btnReject.disabled = true;
                btnReset.disabled = true;

                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', action);

                fetch('action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    targetBtn.innerText = originalText;
                    
                    btnAccept.disabled = false;
                    btnReject.disabled = false;
                    btnReset.disabled = false;

                    if (data.success) {
                        const li = document.getElementById('item-' + id);
                        if (li) {
                            if (action === 'accept') {
                                li.style.backgroundColor = '#d4edda';
                                btnAccept.style.display = 'none';
                                btnReject.style.display = 'inline-block';
                                btnReset.style.display = 'inline-block';
                            } else if (action === 'reject') {
                                li.style.backgroundColor = '#f8d7da';
                                btnReject.style.display = 'none';
                                btnAccept.style.display = 'inline-block';
                                btnReset.style.display = 'inline-block';
                            } else { // reset
                                li.style.backgroundColor = 'white';
                                btnReset.style.display = 'none';
                                btnAccept.style.display = 'inline-block';
                                btnReject.style.display = 'inline-block';
                            }
                        }
                    } else {
                        alert('Error updating item.');
                    }
                })
                .catch(err => {
                    alert('Network error.');
                    targetBtn.innerText = originalText;
                    btnAccept.disabled = false;
                    btnReject.disabled = false;
                    btnReset.disabled = false;
                });
            }
        </script>
    <?php else: ?>
        <p>No news found for these filters.</p>
    <?php endif; ?>

    <div style="margin-top: 40px; padding: 20px 0; border-top: 1px solid #ccc; text-align: center; color: #777; font-size: 12px; display: flex; flex-direction: column; align-items: center; gap: 10px;">
        <div>&copy; <?= date('Y') ?> RSS Helper. All rights reserved.</div>
        <a href="https://shirokhorshideiran.org/" target="_blank" style="text-decoration: none; color: #777; display: flex; align-items: center; gap: 5px;">
            <img src="https://www.google.com/s2/favicons?domain=shirokhorshideiran.org&sz=32" alt="Logo" style="width: 16px; height: 16px;">
            Shirokhorshideiran.org
        </a>
    </div>
</body>
</html>

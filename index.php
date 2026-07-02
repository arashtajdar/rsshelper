<?php
require_once 'config.php';
requireAuth();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_source = $_GET['source'] ?? '';
$selected_search = $_GET['search'] ?? '';
$selected_status = $_GET['status'] ?? '0';

$current_page = max(1, (int)($_GET['page'] ?? 1));
$items_per_page = 50;

// Build query with optional source filter
$current_user_id = $_SESSION['user_id'];

$where_clause = "WHERE DATE(news.published) = :date";
$params = [
    ':date' => $selected_date,
    ':current_user_id' => $current_user_id
];

if ($selected_status !== 'all') {
    $where_clause .= " AND COALESCE(user_news_status.status, 0) = :status";
    $params[':status'] = (int) $selected_status;
}

if ($selected_source !== '') {
    $where_clause .= " AND source_id = :source";
    $params[':source'] = (int) $selected_source;
}

if ($selected_search !== '') {
    $where_clause .= " AND title LIKE :search";
    $params[':search'] = '%' . $selected_search . '%';
}

$count_query = "SELECT COUNT(*) FROM news LEFT JOIN user_news_status ON news.id = user_news_status.news_id AND user_news_status.user_id = :current_user_id $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_items = (int) $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);
if ($total_pages == 0) $total_pages = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

$limit = (int) $items_per_page;
$offset = (int) (($current_page - 1) * $items_per_page);

$query = "SELECT news.*, COALESCE(user_news_status.status, 0) AS status 
          FROM news 
          LEFT JOIN user_news_status ON news.id = user_news_status.news_id AND user_news_status.user_id = :current_user_id 
          $where_clause 
          ORDER BY id DESC 
          LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available sources for the dropdown is no longer a DB query, we use $news_sources from config.

?>
<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Dashboard</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            background: #f9f9f9;
        }

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

        .nav-links a, .nav-links span {
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #007bff;
        }

        .nav-links .active-link {
            color: #007bff;
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 0 5px;
        }
        
        .page-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .btn-fetch {
            background-color: #10b981;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-fetch:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.25);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            margin-bottom: 20px;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            color: #495057;
            background: white;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background-color: #f8f9fa;
            border-color: #c1c9d0;
            color: #007bff;
        }

        .pagination .active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination .disabled, .pagination .ellipsis {
            background-color: transparent;
            border-color: transparent;
            color: #adb5bd;
            cursor: default;
        }

        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .news-list {
            list-style: none;
            padding: 0;
        }

        .news-item {
            background: white;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .news-item label {
            display: block;
            cursor: pointer;
        }

        .news-item input[type="checkbox"] {
            margin-right: 10px;
        }

        .news-item a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }

        .news-item a:hover {
            text-decoration: underline;
        }

        .source-badge {
            display: inline-block;
            padding: 2px 6px;
            background: #eee;
            color: #555;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 8px;
            font-weight: bold;
        }

        button,
        .btn {
            padding: 8px 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        button:hover,
        .btn:hover {
            background: #0056b3;
        }

        .btn-accept {
            background: #28a745;
            padding: 4px 8px;
            font-size: 12px;
        }

        .btn-accept:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            padding: 4px 8px;
            font-size: 12px;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-reset:hover {
            background: #5a6268;
        }

        select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        /* old nav styles removed */

        /* Mobile Responsive */
        @media (max-width: 600px) {
            body {
                margin: 10px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .top-navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .nav-links, .user-info {
                flex-wrap: wrap;
                justify-content: center;
            }

            .filter-bar form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-bar input[type="text"],
            .filter-bar input[type="date"],
            .filter-bar select,
            .filter-bar button {
                width: 100%;
                box-sizing: border-box;
            }

            .news-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .news-actions {
                align-self: stretch;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }

            .news-actions button {
                flex: 1;
                padding: 10px;
                font-size: 14px;
            }

            .source-badge {
                display: block;
                margin-bottom: 5px;
                width: fit-content;
            }
        }
    </style>
</head>

<body>
    <div class="top-navbar">
        <div class="nav-links">
            <span class="active-link">Dashboard</span>
            <a href="export.php">Export / Archive</a>
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

    <?php
    $today_str = date('Y-m-d');
    $yesterday_str = date('Y-m-d', strtotime('-1 day'));
    $can_fetch = ($selected_date === $today_str || $selected_date === $yesterday_str);
    ?>
    <div class="page-header">
        <h2>News for <?= htmlspecialchars($selected_date) ?></h2>

        <?php if ($can_fetch): ?>
            <form method="POST" action="fetch.php" style="margin: 0;">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                <button type="submit" class="btn-fetch">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Fetch Latest News
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['admin_fetch_log']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div
            style="background: #e8f4f8; padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #bce8f1; color: #31708f;">
            <h3 style="margin-top: 0;">Fetch Log Summary</h3>
            <p><strong>Total Successfully Inserted:</strong> <?= $_SESSION['admin_fetch_log']['total_success'] ?> <br>
                <strong>Total Feed Errors:</strong> <?= $_SESSION['admin_fetch_log']['total_errors'] ?>
            </p>

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
                            <td style="padding: 8px; border: 1px solid #bce8f1; font-weight: bold;">
                                <?= htmlspecialchars($agency) ?></td>
                            <td style="padding: 8px; border: 1px solid #bce8f1;"><?= $stats['fetched'] ?></td>
                            <td
                                style="padding: 8px; border: 1px solid #bce8f1; color: <?= $stats['error'] ? '#a94442' : '#3c763d' ?>;">
                                <?= $stats['error'] ? htmlspecialchars($stats['error']) : 'None' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php unset($_SESSION['admin_fetch_log']); ?>
    <?php endif; ?>

    <div class="filter-bar"
        style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="GET" action="index.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>"
                style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
            <select name="status">
                <option value="0" <?= $selected_status === '0' ? 'selected' : '' ?>>Pending</option>
                <option value="1" <?= $selected_status === '1' ? 'selected' : '' ?>>Accepted</option>
                <option value="2" <?= $selected_status === '2' ? 'selected' : '' ?>>Rejected</option>
                <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
            </select>
            <select name="source">
                <option value="">All Sources</option>
                <?php foreach ($news_sources as $src_id => $src_data): ?>
                    <option value="<?= $src_id ?>" <?= (string) $src_id === $selected_source ? 'selected' : '' ?>>
                        <?= htmlspecialchars($src_data['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" value="<?= htmlspecialchars($selected_search) ?>"
                placeholder="Search titles..."
                style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; flex-grow: 1; min-width: 200px;">
            <button type="submit">Apply Filters</button>
        </form>
    </div>

    <?php if (count($news_items) > 0): ?>
        <?php $news_count = $total_items - (($current_page - 1) * $items_per_page); ?>
        <ul class="news-list">
            <?php foreach ($news_items as $item):
                $bg_color = 'white';
                if ($item['status'] == 1)
                    $bg_color = '#d4edda';
                if ($item['status'] == 2)
                    $bg_color = '#f8d7da';
                ?>
                <li class="news-item" id="item-<?= $item['id'] ?>"
                    style="background-color: <?= $bg_color ?>; transition: background-color 0.3s ease;">
                    <div class="news-content" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1; padding-right: 15px;">
                            <div style="margin-bottom: 4px;">
                                <?php $badge_color = $source_colors[$item['source_id']] ?? '#eee'; ?>
                                <span class="source-badge"
                                    style="background-color: <?= $badge_color ?>; border: 1px solid rgba(0,0,0,0.1); vertical-align: middle;"><?= htmlspecialchars($item['source'] ?? 'Unknown') ?></span>
                                <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank"
                                    style="line-height: 1.4; display: inline-block; font-size: 1.05em; font-weight: bold; vertical-align: middle;"><?= $news_count-- ?>.
                                    <?= htmlspecialchars($item['title']) ?></a>
                            </div>
                            <div style="font-size: 0.8em; color: #666; margin-bottom: 4px;">
                                <?= $item['published'] ? '<strong>' . htmlspecialchars(date('M j, Y, g:i a', strtotime($item['published']))) . '</strong>' : '' ?>
                                <?= $item['author'] ? ' &bull; by ' . htmlspecialchars($item['author']) : '' ?>
                            </div>
                            <?php if ($item['description']): ?>
                                <div style="font-size: 0.85em; color: #555; line-height: 1.3; max-width: 900px;">
                                    <?= htmlspecialchars($item['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="news-actions" style="white-space: nowrap; margin-left: 10px;">
                            <button type="button" id="btn-accept-<?= $item['id'] ?>"
                                onclick="triage(<?= $item['id'] ?>, 'accept')" class="btn-accept"
                                style="<?= $item['status'] == 1 ? 'display: none;' : '' ?>">Accept</button>
                            <button type="button" id="btn-reject-<?= $item['id'] ?>"
                                onclick="triage(<?= $item['id'] ?>, 'reject')" class="btn-reject"
                                style="<?= $item['status'] == 2 ? 'display: none;' : '' ?>">Reject</button>
                            <button type="button" id="btn-reset-<?= $item['id'] ?>"
                                onclick="triage(<?= $item['id'] ?>, 'reset')" class="btn-reset"
                                style="<?= $item['status'] == 0 ? 'display: none;' : '' ?>">Undo</button>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($total_pages > 1): ?>
            <?php
            // Build query string for pagination links
            $query_params = $_GET;
            unset($query_params['page']);
            $base_url = '?' . http_build_query($query_params) . '&page=';
            ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="<?= htmlspecialchars($base_url . ($current_page - 1)) ?>">&laquo; Previous</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Previous</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="' . htmlspecialchars($base_url . '1') . '">1</a>';
                    if ($start_page > 2) echo '<span class="ellipsis">...</span>';
                }
                
                for ($p = $start_page; $p <= $end_page; $p++) {
                    if ($p == $current_page) {
                        echo '<span class="active">' . $p . '</span>';
                    } else {
                        echo '<a href="' . htmlspecialchars($base_url . $p) . '">' . $p . '</a>';
                    }
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span class="ellipsis">...</span>';
                    echo '<a href="' . htmlspecialchars($base_url . $total_pages) . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?= htmlspecialchars($base_url . ($current_page + 1)) ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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

    <?php require 'footer.php'; ?>
</body>

</html>
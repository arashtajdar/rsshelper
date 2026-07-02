<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$fetch_date = $_POST['date'] ?? date('Y-m-d');
$api_key = getenv('CURRENTS_API_KEY') ?: $_ENV['CURRENTS_API_KEY'] ?? '';

$success_count = 0;
$error_count = 0;

$admin_log = [];

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

foreach ($news_sources as $source_id => $source_data) {
    $agency_stats = ['fetched' => 0, 'error' => null];
    $source_name = $source_data['name'];
    $domain = $source_data['domain'];
    
    $baseUrl = "https://api.currentsapi.services/v1/latest-news";
    
    for ($page = 1; $page <= 2; $page++) {
        $queryParams = [
            'domain' => $domain,
            'language' => 'en',
            'apiKey' => $api_key,
            'page_number' => $page
        ];
        $url = $baseUrl . '?' . http_build_query($queryParams);
        
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        
        if ($curl_error) {
            $agency_stats['error'] = "cURL Error (Page $page): " . $curl_error;
            $error_count++;
            logMessage("Failed to fetch $source_name: cURL error $curl_error");
            break;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['status']) || $data['status'] !== 'ok') {
            if (!$agency_stats['error']) {
                $agency_stats['error'] = "API Error (Page $page): " . ($data['msg'] ?? 'Unknown');
                $error_count++;
            }
            logMessage("Failed to fetch $source_name: " . json_encode($data));
            break;
        }
        
        $news = $data['news'] ?? [];
        if (empty($news)) {
            break;
        }
        
        $found_duplicate = false;
        
        foreach ($news as $item) {
            $news_uuid = $item['id'] ?? '';
            $title = $item['title'] ?? '';
            $description = $item['description'] ?? '';
            $link = $item['url'] ?? '';
            $author = $item['author'] ?? '';
            $published_str = $item['published'] ?? '';
            
            if (!$news_uuid || !$title || !$link) continue;
            
            $published = null;
            if ($published_str) {
                $parsed_time = strtotime($published_str);
                if ($parsed_time) {
                    $published = date('Y-m-d H:i:s', $parsed_time);
                }
            }
            
            // Check if duplicate
            $checkStmt = $db->prepare("SELECT id FROM news WHERE news_id = :news_id");
            $checkStmt->execute([':news_id' => $news_uuid]);
            if ($checkStmt->rowCount() > 0) {
                // Found a duplicate, meaning we reached already fetched news for this source.
                $found_duplicate = true;
                break;
            }
            
            try {
                $stmt = $db->prepare("INSERT INTO news (news_id, title, description, link, author, published, source, source_id, status) VALUES (:news_id, :title, :description, :link, :author, :published, :source, :source_id, 0)");
                $stmt->execute([
                    ':news_id' => $news_uuid,
                    ':title' => $title,
                    ':description' => $description,
                    ':link' => $link,
                    ':author' => $author,
                    ':published' => $published,
                    ':source' => $source_name,
                    ':source_id' => $source_id
                ]);
                if ($stmt->rowCount() > 0) {
                    $success_count++;
                    $agency_stats['fetched']++;
                }
            } catch (PDOException $e) {
                logMessage("Database insert error for $link: " . $e->getMessage());
                if (!$agency_stats['error']) {
                    $agency_stats['error'] = "Database insert error.";
                }
            }
        }
        
        if ($found_duplicate) {
            break; // Stop fetching more pages for this source
        }
    }

    $admin_log[$source_name] = $agency_stats;
    logMessage("Successfully processed: $source_name");
}

curl_close($ch);

// Log fetch action in user history
try {
    $hist_stmt = $db->prepare("INSERT INTO action_history (user_id, news_id, action_type) VALUES (:user_id, NULL, 'fetch')");
    $hist_stmt->execute([':user_id' => $_SESSION['user_id']]);
} catch (PDOException $e) {
    logMessage("Failed to log fetch action history: " . $e->getMessage());
}

logMessage("Fetch complete. Items processed (attempted insert): $success_count. Feed errors: $error_count");

$_SESSION['admin_fetch_log'] = [
    'details' => $admin_log,
    'total_success' => $success_count,
    'total_errors' => $error_count
];

header("Location: index.php?date=" . urlencode($fetch_date));
die();

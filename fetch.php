<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$fetch_date = $_POST['date'] ?? date('Y-m-d');

// Set stream context for timeout
$ctx = stream_context_create([
    'http' => [
        'timeout' => 5 // 5 seconds timeout
    ]
]);

$success_count = 0;
$error_count = 0;

$admin_log = [];

foreach ($rss_feeds as $source_name => $feed_url) {
    $agency_stats = ['fetched' => 0, 'error' => null];
    
    $xml_string = @file_get_contents($feed_url, false, $ctx);

    if ($xml_string === false) {
        $error = error_get_last();
        $error_msg = $error ? $error['message'] : "Failed to fetch feed";
        logMessage("Failed to fetch feed: $feed_url");
        $agency_stats['error'] = "HTTP Error: " . $error_msg;
        $admin_log[$source_name] = $agency_stats;
        $error_count++;
        continue;
    }

    $rss = @simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($rss === false) {
        $error = error_get_last();
        $error_msg = $error ? $error['message'] : "Failed to parse feed";
        logMessage("Failed to parse feed: $feed_url");
        $agency_stats['error'] = "XML Parse Error: " . $error_msg;
        $admin_log[$source_name] = $agency_stats;
        $error_count++;
        continue;
    }

    $items = [];
    if (isset($rss->channel->item)) {
        $items = $rss->channel->item;
    } elseif (isset($rss->item)) {
        // RSS 1.0
        $items = $rss->item;
    } elseif (isset($rss->entry)) {
        // Atom
        $items = $rss->entry;
    }

    foreach ($items as $item) {
        // Depending on format, title and link can be accessed differently. We assume standard RSS.
        $title = (string) ($item->title ?? '');
        $link = (string) ($item->link ?? '');

        // Sometimes Atom uses attributes for link
        if (empty($link) && isset($item->link['href'])) {
            $link = (string) $item->link['href'];
        }

        // Extract and parse date
        $pubDate = '';
        if (isset($item->pubDate)) {
            $pubDate = (string) $item->pubDate;
        } elseif (isset($item->updated)) {
            $pubDate = (string) $item->updated;
        } elseif (isset($item->children('http://purl.org/dc/elements/1.1/')->date)) {
            $pubDate = (string) $item->children('http://purl.org/dc/elements/1.1/')->date;
        }

        if ($title && $link) {
            $item_date = $fetch_date; // Fallback to fetch date if no date found
            if ($pubDate) {
                $parsed_time = strtotime($pubDate);
                if ($parsed_time) {
                    $item_date = date('Y-m-d', $parsed_time);
                }
            }

            // Insert the article using the date it was actually published, or today's date
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO news (title, link, status, created_date, source) VALUES (:title, :link, 0, :date, :source)");
                $stmt->execute([
                    ':title' => $title,
                    ':link' => $link,
                    ':date' => $item_date,
                    ':source' => $source_name
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
    }

    $admin_log[$source_name] = $agency_stats;
    logMessage("Successfully processed feed: $feed_url ($source_name)");
}

logMessage("Fetch complete. Items processed (attempted insert): $success_count. Feed errors: $error_count");

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $_SESSION['admin_fetch_log'] = [
        'details' => $admin_log,
        'total_success' => $success_count,
        'total_errors' => $error_count
    ];
}

header("Location: index.php?date=" . urlencode($fetch_date));
die();

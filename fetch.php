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

foreach ($rss_feeds as $source_name => $feed_url) {
    $xml_string = @file_get_contents($feed_url, false, $ctx);

    if ($xml_string === false) {
        logMessage("Failed to fetch feed: $feed_url");
        $error_count++;
        continue;
    }

    $rss = @simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);

    if ($rss === false) {
        logMessage("Failed to parse feed: $feed_url");
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

            // Only insert if the article's publish date matches the requested date
            if ($item_date === $fetch_date) {
                try {
                    $stmt = $db->prepare("INSERT OR IGNORE INTO news (title, link, status, created_date, source) VALUES (:title, :link, 0, :date, :source)");
                    $stmt->execute([
                        ':title' => $title,
                        ':link' => $link,
                        ':date' => $fetch_date,
                        ':source' => $source_name
                    ]);
                    $success_count++;
                } catch (PDOException $e) {
                    logMessage("Database insert error for $link: " . $e->getMessage());
                }
            }
        }
    }

    logMessage("Successfully processed feed: $feed_url ($source_name)");
}

logMessage("Fetch complete. Items processed (attempted insert): $success_count. Feed errors: $error_count");

header("Location: index.php?date=" . urlencode($fetch_date));
die();

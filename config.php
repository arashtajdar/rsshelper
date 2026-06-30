<?php
session_start();

// Configuration
define('ACCESS_KEY', getenv('ACCESS_KEY') ?: 'secret123'); // Fallback for testing

$rss_feeds = [
    // US News
    'CNN' => 'http://rss.cnn.com/rss/cnn_topstories.rss',
    'Fox News' => 'https://moxie.foxnews.com/google-publisher/latest.xml',
    'New York Times' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml',
    'New York Post' => 'https://nypost.com/feed/',
    'Washington Times' => 'https://www.washingtontimes.com/rss/headlines/news/',
    'Washington Post' => 'https://feeds.washingtonpost.com/rss/world',
    'OAN' => 'https://www.oann.com/feed/',
    
    // Europe News
    'Guardian' => 'https://www.theguardian.com/world/rss',
    'Spiegel' => 'https://www.spiegel.de/international/index.rss',
    'France 24' => 'https://www.france24.com/en/rss',
    'BBC World' => 'https://feeds.bbci.co.uk/news/world/rss.xml',
    
    // Check Occasionally
    'Wall Street Journal' => 'https://feeds.a.dj.com/rss/RSSWorldNews.xml',
    'Axios' => 'https://api.axios.com/feed/',
];

$source_colors = [
    'CNN' => '#ffe6e6',                 // Red
    'Fox News' => '#e6f2ff',            // Blue
    'New York Times' => '#f2f2f2',      // Black/White
    'New York Post' => '#ffebf0',       // Red/Pink
    'Washington Times' => '#e6e6ff',    // Dark Blue
    'Washington Post' => '#e6f9ff',     // Light Cyan
    'OAN' => '#e6f7ff',                 // Light Blue
    'Guardian' => '#ffffe6',            // Yellow/Blue
    'Spiegel' => '#fff0e6',             // Orange
    'France 24' => '#e6ffff',           // Cyan
    'BBC World' => '#ffe6f2',           // Red
    'Wall Street Journal' => '#f0f0f0', // Black/White
    'Axios' => '#e6ffe6'                // Teal/Green
];

// Paths
$is_railway = getenv('RAILWAY_ENVIRONMENT_NAME') || getenv('RAILWAY_ENVIRONMENT');
$volume_path = getenv('RAILWAY_VOLUME_MOUNT_PATH');
$db_dir = $volume_path ? $volume_path . '/db' : ($is_railway ? '/tmp/db' : __DIR__ . '/db');
$log_dir = $volume_path ? $volume_path . '/logs' : ($is_railway ? '/tmp/logs' : __DIR__ . '/logs');

if (!is_dir($db_dir)) {
    if (!@mkdir($db_dir, 0777, true)) {
        die("Failed to create directory: $db_dir. Please ensure it is writable.");
    }
}

if (!is_dir($log_dir)) {
    if (!@mkdir($log_dir, 0777, true)) {
        die("Failed to create directory: $log_dir. Please ensure it is writable.");
    }
}

define('DB_PATH', $db_dir . '/database.sqlite');
define('LOG_PATH', $log_dir . '/app.log');

// Setup Database
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table
    $db->exec("CREATE TABLE IF NOT EXISTS news (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        link TEXT UNIQUE,
        status INTEGER DEFAULT 0,
        created_date DATE,
        source TEXT
    )");

    // Handle existing databases gracefully
    try {
        $db->exec("ALTER TABLE news ADD COLUMN source TEXT");
    } catch (PDOException $e) {
        // Column probably already exists, which is fine
    }

    // Create index
    $db->exec("CREATE INDEX IF NOT EXISTS idx_news_date_status ON news (created_date, status)");
} catch (Exception $e) {
    die("Database setup failed: " . $e->getMessage());
}

// Authentication Gatekeeper
function requireAuth() {
    if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        die('Access Denied');
    }
}

function logMessage($message) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(LOG_PATH, "[$date] $message\n", FILE_APPEND);
}

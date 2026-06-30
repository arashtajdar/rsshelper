<?php
session_start();

// Configuration
define('ACCESS_KEY', getenv('ACCESS_KEY') ?: 'secret123'); // Fallback for testing

$rss_feeds = [
    'https://news.ycombinator.com/rss',
    'https://feeds.bbci.co.uk/news/world/rss.xml',
];

// Paths
$is_railway = getenv('RAILWAY_ENVIRONMENT_NAME') || getenv('RAILWAY_ENVIRONMENT');
$db_dir = $is_railway ? '/data' : __DIR__ . '/db';
$log_dir = $is_railway ? '/data' : __DIR__ . '/logs';

if (!is_dir($db_dir)) {
    @mkdir($db_dir, 0777, true);
}
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
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
        created_date DATE
    )");

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

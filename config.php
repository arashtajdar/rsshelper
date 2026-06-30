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

$log_dir = $is_railway ? '/tmp/logs' : __DIR__ . '/logs';

if (!is_dir($log_dir)) {
    if (!@mkdir($log_dir, 0777, true)) {
        die("Failed to create directory: $log_dir. Please ensure it is writable.");
    }
}

define('LOG_PATH', $log_dir . '/app.log');

// Setup MySQL Database
$mysql_url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
if ($mysql_url) {
    $parsed = parse_url($mysql_url);
    $db_host = $parsed['host'] ?? '127.0.0.1';
    $db_port = $parsed['port'] ?? 3306;
    $db_user = $parsed['user'] ?? 'root';
    $db_pass = $parsed['pass'] ?? '';
    $db_name = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'rsshelper';
} else {
    $db_host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
    $db_port = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: '3306';
    $db_user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: 'root';
    $db_pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
    $db_name = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'rsshelper';
}

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $db = new PDO($dsn, $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table
    $db->exec("CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title TEXT,
        link VARCHAR(255) UNIQUE,
        status INT DEFAULT 0,
        created_date DATE,
        source VARCHAR(255)
    )");

    // Handle existing databases gracefully
    try {
        $db->exec("ALTER TABLE news ADD COLUMN source VARCHAR(255)");
    } catch (PDOException $e) {
        // Column probably already exists, which is fine
    }

    // Create index
    try {
        $db->exec("CREATE INDEX idx_news_date_status ON news (created_date, status)");
    } catch (PDOException $e) {
        // Index probably already exists
    }
} catch (Exception $e) {
    if ($is_railway && $db_host === '127.0.0.1') {
        die("Database connection failed. It looks like the MySQL connection variables (MYSQL_URL, MYSQLHOST) were not passed to your app. Did you remember to link the MySQL database to this service in Railway? Error: " . $e->getMessage());
    }
    die("Database setup failed on host $db_host:$db_port. Error: " . $e->getMessage());
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

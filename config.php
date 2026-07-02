<?php
session_start();

// Configuration
define('ACCESS_KEY', getenv('ACCESS_KEY') ?: 'secret123'); // Fallback for testing

define('SRC_CNN', 1);
define('SRC_FOX', 2);
define('SRC_NYT', 3);
define('SRC_NYP', 4);
define('SRC_WT', 5);
define('SRC_WP', 6);
define('SRC_OAN', 7);
define('SRC_GUARDIAN', 8);
define('SRC_SPIEGEL', 9);
define('SRC_FRANCE24', 10);
define('SRC_BBC', 11);
define('SRC_WSJ', 12);
define('SRC_AXIOS', 13);

$news_sources = [
    SRC_CNN => ['name' => 'CNN', 'domain' => 'cnn.com', 'logo' => 'cnn.png'],
    SRC_FOX => ['name' => 'Fox News', 'domain' => 'foxnews.com', 'logo' => 'fox_news.png'],
    SRC_NYT => ['name' => 'New York Times', 'domain' => 'nytimes.com', 'logo' => 'new_york_times.png'],
    SRC_NYP => ['name' => 'New York Post', 'domain' => 'nypost.com', 'logo' => 'new_york_post.png'],
    SRC_WT => ['name' => 'Washington Times', 'domain' => 'washingtontimes.com', 'logo' => 'washington_times.png'],
    SRC_WP => ['name' => 'Washington Post', 'domain' => 'washingtonpost.com', 'logo' => 'washington_post.png'],
    SRC_OAN => ['name' => 'OAN', 'domain' => 'oann.com', 'logo' => 'oan.png'],
    SRC_GUARDIAN => ['name' => 'Guardian', 'domain' => 'theguardian.com', 'logo' => 'guardian.png'],
    SRC_SPIEGEL => ['name' => 'Spiegel', 'domain' => 'spiegel.de', 'logo' => 'spiegel.png'],
    SRC_FRANCE24 => ['name' => 'France 24', 'domain' => 'france24.com', 'logo' => 'france24.png'],
    SRC_BBC => ['name' => 'BBC World', 'domain' => 'bbc.com', 'logo' => 'bbc_world.png'],
    SRC_WSJ => ['name' => 'Wall Street Journal', 'domain' => 'wsj.com', 'logo' => 'wall_street_journal.png'],
    SRC_AXIOS => ['name' => 'Axios', 'domain' => 'axios.com', 'logo' => 'axios.png'],
];

$source_colors = [
    SRC_CNN => '#ffe6e6',
    SRC_FOX => '#e6f2ff',
    SRC_NYT => '#f2f2f2',
    SRC_NYP => '#ffebf0',
    SRC_WT => '#e6e6ff',
    SRC_WP => '#e6f9ff',
    SRC_OAN => '#e6f7ff',
    SRC_GUARDIAN => '#ffffe6',
    SRC_SPIEGEL => '#fff0e6',
    SRC_FRANCE24 => '#e6ffff',
    SRC_BBC => '#ffe6f2',
    SRC_WSJ => '#f0f0f0',
    SRC_AXIOS => '#e6ffe6'
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
} catch (Exception $e) {
    if ($is_railway && $db_host === '127.0.0.1') {
        die("Database connection failed. It looks like the MySQL connection variables (MYSQL_URL, MYSQLHOST) were not passed to your app. Did you remember to link the MySQL database to this service in Railway? Error: " . $e->getMessage());
    }
    die("Database setup failed on host $db_host:$db_port. Error: " . $e->getMessage());
}



// Authentication Gatekeeper
function requireAuth() {
    if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || !isset($_SESSION['user_id'])) {
        header("Location: auth.php");
        die('Access Denied');
    }
}

function requireAdmin() {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('403 Forbidden - Admin access required');
    }
}


function logMessage($message) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(LOG_PATH, "[$date] $message\n", FILE_APPEND);
}

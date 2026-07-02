<?php
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
            $_ENV[trim($name)] = trim($value);
        }
    }
}
$rss_feeds = [
    'CNN' => 'cnn.com',
    'Fox News' => 'foxnews.com',
    'New York Times' => 'nytimes.com',
    'New York Post' => 'nypost.com',
    'Washington Times' => 'washingtontimes.com',
    'Washington Post' => 'washingtonpost.com',
    'OAN' => 'oann.comID',
    'Guardian' => 'theguardian.com',
    'Spiegel' => 'spiegel.de',
    'France 24' => 'france24.com',
    'BBC World' => 'bbc.com',
    'Wall Street Journal' => 'wsj.com',
    'Axios' => 'axios.com',
];
$baseUrl = "https://api.currentsapi.services/v1/search";
$queryParams = [
    'domain' => 'cnn.com,cnn,edition.cnn.com,axios.com',
    'language' => 'en',
    'start_date' => gmdate('Y-m-d\T00:00:00\Z'),
    'end_date' => gmdate('Y-m-d\T23:59:59\Z'),
    'apiKey' => getenv('CURRENTS_API_KEY'),
];
$url = $baseUrl . '?' . http_build_query($queryParams);
echo "URL: " . htmlspecialchars($url) . "<br>\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data && isset($data['status']) && $data['status'] === 'ok') {
    $news = $data['news'] ?? [];

    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; font-family: sans-serif;'>";
    echo "<thead style='background-color: #f2f2f2;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Title</th>";
    echo "<th>Description</th>";
    echo "<th>URL</th>";
    echo "<th>Author</th>";
    echo "<th>Published</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    foreach ($news as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($item['title'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($item['description'] ?? '') . "</td>";
        echo "<td><a href='" . htmlspecialchars($item['url'] ?? '#') . "' target='_blank'>Link</a></td>";
        echo "<td>" . htmlspecialchars($item['author'] ?? '') . "</td>";
        $pub = htmlspecialchars($item['published'] ?? '');
        echo "<td><script>document.write(new Date('$pub').toLocaleString(undefined, {dateStyle: 'medium', timeStyle: 'short'}));</script><noscript>$pub</noscript></td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
} else {
    echo "Status: " . htmlspecialchars($data['status'] ?? '') . "<br>";
    echo "Message: " . htmlspecialchars($data['msg'] ?? '') . "<br>";
    if (isset($data['details']['errors']) && is_array($data['details']['errors'])) {
        echo "Details:<br>";
        foreach ($data['details']['errors'] as $key => $errorMsg) {
            echo "- <strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($errorMsg) . "<br>";
        }
    }
}

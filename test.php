<?php

$baseUrl = "https://api.currentsapi.services/v1/search";
$queryParams = [
    'domain'      => 'wsj.com',
    'language'    => 'en',
    'type'        => 1,
    'start_date'  => gmdate('Y-m-d\T00:00:00\Z'),
    'end_date'    => gmdate('Y-m-d\T23:59:59\Z'),
    'page_number' => 1,
    'page_size'   => 20,
    'apiKey'      => getenv('CURRENTS_API_KEY'),
];
$url = $baseUrl . '?' . http_build_query($queryParams);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
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
    echo "Failed to fetch or parse news data.<br>";
    if (is_array($data)) {
        echo "<strong>API Response:</strong><pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    } else {
        echo "<strong>Raw Response:</strong><pre>" . htmlspecialchars($response) . "</pre>";
    }
}

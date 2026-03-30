<?php
header('Content-Type: application/json');

$TOKEN = "usr-IwGPkX2IxDri-ogxJr6BpxIyj9P-_dyV4gce1lx7_-w";

if (!isset($_GET['species'])) {
    echo json_encode(['error' => 'No species provided']);
    exit;
}

$species = urlencode($_GET['species']);
$url = "https://trefle.io/api/v1/species/search?q={$species}&limit=1&token={$TOKEN}";

// Use file_get_contents to fetch API data
$options = [
    "http" => [
        "header" => "User-Agent: PHP\r\n"
    ]
];

$context = stream_context_create($options);
$result = @file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo json_encode(['error' => 'Failed to fetch data']);
    exit;
}

// Return API response as-is
echo $result;
?>

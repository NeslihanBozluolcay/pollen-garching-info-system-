<?php
set_time_limit(30);
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url = 'https://www.donnerwetter.de/pollenflug/garching/DE16830.html';

$ctx = stream_context_create(array(
    'http' => array(
        'method'  => 'GET',
        'timeout' => 10,
        'header'  => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\nCookie: __cmpcc=1; gdpr=1\r\n",
        'ignore_errors' => true,
    ),
    'ssl' => array(
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ),
));

$html = @file_get_contents($url, false, $ctx);

if ($html === false) {
    echo json_encode(array('error' => 'fetch failed'));
    exit;
}


$parts = preg_split('/(pollg[bgk]\.gif)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

$catNames  = array('b' => 'Tree', 'g' => 'Grass', 'k' => 'Weed');
$lvlLabels = array('0' => 'None', '1' => 'Low', '2' => 'Moderate', '3' => 'High', '4' => 'Very High');


$nameTranslations = array(
    'Erle'          => 'Alder',
    'Hasel'         => 'Hazel',
    'Birke'         => 'Birch',
    'Buche'         => 'Beech',
    'Eiche'         => 'Oak',
    'Esche'         => 'Ash',
    'Ulme'          => 'Elm',
    'Pappel'        => 'Poplar',
    'Weide'         => 'Willow',
    'Ahorn'         => 'Maple',
    'Linde'         => 'Linden',
    'Platane'       => 'Plane Tree',
    'Flieder'       => 'Lilac',
    'Holunder'      => 'Elder',
    'Kiefer'        => 'Pine',
    'Tanne'         => 'Fir',
    'Fichte'        => 'Spruce',
    'Gr&auml;ser'   => 'Grasses',
    'Gräser'        => 'Grasses',
    'Roggen'        => 'Rye',
    'Weizen'        => 'Wheat',
    'Gerste'        => 'Barley',
    'Hafer'         => 'Oat',
    'Mais'          => 'Corn',
    'Raps'          => 'Rapeseed',
    'Beifuß'        => 'Mugwort',
    'Beifuss'       => 'Mugwort',
    'Ambrosia'      => 'Ragweed',
    'Brennessel'    => 'Nettle',
    'Nessel'        => 'Nettle',
    'Spitzwegerich' => 'Plantain',
    'Löwenzahn'     => 'Dandelion',
    'Loewenzahn'    => 'Dandelion',
    'Gänsefuß'      => 'Goosefoot',
    'Hopfen'        => 'Hop',
);

$pollen = array();

for ($i = 1; $i < count($parts) - 1; $i += 2) {
    $catGif = $parts[$i];   // "pollgb.gif", "pollgg.gif", or "pollgk.gif"
    $chunk  = $parts[$i+1]; // HTML until the next pollg*.gif

  
    preg_match('/pollg([bgk])\.gif/i', $catGif, $cm);
    if (!isset($cm[1])) continue;
    $cat = strtolower($cm[1]);

    
    if (!preg_match('/<b>[^<]*(?:<font[^>]*>)?([^<]+)/i', $chunk, $nm)) continue;
    $name = trim($nm[1]);
    if (strlen($name) < 2) continue;

    // Level: poll[0-4].gif
    if (!preg_match('/poll([0-4])\.gif/i', $chunk, $lm)) continue;
    $lvl = (int)$lm[1];

    $englishName = isset($nameTranslations[$name]) ? $nameTranslations[$name] : $name;

    $pollen[] = array(
        'category'      => $cat,
        'category_name' => isset($catNames[$cat]) ? $catNames[$cat] : '?',
        'name'          => $englishName,
        'level'         => $lvl,
        'level_label'   => isset($lvlLabels[$lvl]) ? $lvlLabels[$lvl] : '?',
    );
}


$active = array();
foreach ($pollen as $p) {
    if ($p['level'] > 0) {
        $active[] = $p;
    }
}

echo json_encode(array('pollen' => $active, 'count' => count($active), 'total_parsed' => count($pollen)));

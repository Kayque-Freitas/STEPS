<?php
require_once 'config.php';
$videoId = 3;
$url = BASE_URL . 'watch.php?id=' . $videoId;
echo 'URL gerada: ' . $url . "\n";
?>
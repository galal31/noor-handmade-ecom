<?php

require_once __DIR__ . '/includes/app_config.php';

header('Content-Type: text/plain; charset=UTF-8');

$base_url = rtrim(app_base_url(), '/');
$base_path = trim((string) parse_url($base_url, PHP_URL_PATH), '/');
$path_prefix = $base_path !== '' ? '/' . $base_path : '';

$disallowed_paths = [
    '/admin/',
    '/includes/',
    '/storage/',
    '/vendor/',
    '/cart_handler.php',
];

echo "User-agent: *\n";
echo "Allow: /\n";
foreach ($disallowed_paths as $path) {
    echo 'Disallow: ' . $path_prefix . $path . "\n";
}
echo "\n";
echo 'Sitemap: ' . $base_url . "/sitemap.xml\n";

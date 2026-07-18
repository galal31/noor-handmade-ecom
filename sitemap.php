<?php

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/app_config.php';

header('Content-Type: application/xml; charset=UTF-8');

$base_url = rtrim(app_base_url(), '/');
$urls = [
    $base_url . '/',
    $base_url . '/products.php',
    $base_url . '/about.php',
    $base_url . '/faq.php',
    $base_url . '/privacy.php',
    $base_url . '/shipping_returns.php',
];

$category_slugs = $pdo->query("SELECT slug FROM categories WHERE slug IS NOT NULL AND slug != '' ORDER BY id ASC")
    ->fetchAll(PDO::FETCH_COLUMN);
foreach ($category_slugs as $slug) {
    $urls[] = $base_url . '/products.php?category=' . rawurlencode((string) $slug);
}

$product_slugs = $pdo->query("SELECT slug FROM products WHERE slug IS NOT NULL AND slug != '' ORDER BY id ASC")
    ->fetchAll(PDO::FETCH_COLUMN);
foreach ($product_slugs as $slug) {
    $urls[] = $base_url . '/product_details.php?slug=' . rawurlencode((string) $slug);
}

$urls = array_values(array_unique($urls));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";

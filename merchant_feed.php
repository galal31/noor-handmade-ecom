<?php

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/app_config.php';

header('Content-Type: application/xml; charset=UTF-8');

$base_url = rtrim(app_base_url(), '/');
$products = $pdo->query("
    SELECT p.id,
           p.name,
           p.slug,
           p.description,
           p.price,
           p.stock_quantity,
           c.name AS category_name,
           COALESCE(
               p.main_image,
               (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1),
               'placeholder.svg'
           ) AS display_image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.slug IS NOT NULL AND p.slug != ''
    ORDER BY p.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

function merchant_xml(string $value): string
{
    $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
echo "  <channel>\n";
echo "    <title>Noor Handmade</title>\n";
echo '    <link>' . merchant_xml($base_url . '/') . "</link>\n";
echo "    <description>منتجات Noor Handmade اليدوية</description>\n";

foreach ($products as $product) {
    $product_url = $base_url . '/product_details.php?slug=' . rawurlencode((string) $product['slug']);
    $image_url = $base_url . '/images/products/' . rawurlencode((string) $product['display_image']);
    $description = trim(preg_replace(
        '/\s+/u',
        ' ',
        strip_tags(html_entity_decode((string) $product['description'], ENT_QUOTES, 'UTF-8'))
    ));
    if ($description === '') {
        $description = 'منتج يدوي مميز من Noor Handmade: ' . $product['name'];
    }
    $availability = (int) $product['stock_quantity'] > 0 ? 'in_stock' : 'out_of_stock';

    echo "    <item>\n";
    echo '      <g:id>NOOR-' . (int) $product['id'] . "</g:id>\n";
    echo '      <g:title>' . merchant_xml((string) $product['name']) . "</g:title>\n";
    echo '      <g:description>' . merchant_xml($description) . "</g:description>\n";
    echo '      <g:link>' . merchant_xml($product_url) . "</g:link>\n";
    echo '      <g:image_link>' . merchant_xml($image_url) . "</g:image_link>\n";
    echo '      <g:availability>' . $availability . "</g:availability>\n";
    echo '      <g:price>' . number_format((float) $product['price'], 2, '.', '') . " EGP</g:price>\n";
    echo "      <g:condition>new</g:condition>\n";
    echo "      <g:brand>Noor Handmade</g:brand>\n";
    echo "      <g:identifier_exists>no</g:identifier_exists>\n";
    if (!empty($product['category_name'])) {
        echo '      <g:product_type>' . merchant_xml((string) $product['category_name']) . "</g:product_type>\n";
    }
    echo "    </item>\n";
}

echo "  </channel>\n";
echo "</rss>\n";

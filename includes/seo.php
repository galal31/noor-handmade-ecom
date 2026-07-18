<?php

require_once __DIR__ . '/app_config.php';

function seo_absolute_url(string $path = ''): string
{
    $base_url = rtrim(app_base_url(), '/');
    if ($path === '') {
        return $base_url . '/';
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return $base_url . '/' . ltrim($path, '/');
}

function seo_organization_schema(): array
{
    $home_url = seo_absolute_url();
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $home_url . '#organization',
        'name' => 'Noor Handmade',
        'url' => $home_url,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => seo_absolute_url('images/logo.jpeg'),
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+201150926556',
            'contactType' => 'customer service',
            'availableLanguage' => ['ar'],
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'القاهرة',
            'addressCountry' => 'EG',
        ],
    ];
}

function seo_website_schema(): array
{
    $home_url = seo_absolute_url();
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $home_url . '#website',
        'url' => $home_url,
        'name' => 'Noor Handmade',
        'inLanguage' => 'ar-EG',
        'publisher' => [
            '@id' => $home_url . '#organization',
        ],
    ];
}

function seo_breadcrumb_schema(array $items): array
{
    $list_items = [];
    foreach (array_values($items) as $item) {
        if (empty($item['name']) || !array_key_exists('url', $item)) {
            continue;
        }
        $list_items[] = [
            '@type' => 'ListItem',
            'position' => count($list_items) + 1,
            'name' => (string) $item['name'],
            'item' => seo_absolute_url((string) $item['url']),
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list_items,
    ];
}

function seo_product_schema(array $product, array $image_paths, string $canonical_url): array
{
    $images = [];
    foreach ($image_paths as $image_path) {
        $image_url = seo_absolute_url((string) $image_path);
        if (!in_array($image_url, $images, true)) {
            $images[] = $image_url;
        }
    }

    $description = trim(preg_replace(
        '/\s+/u',
        ' ',
        strip_tags(html_entity_decode((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8'))
    ));

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        '@id' => $canonical_url . '#product',
        'name' => (string) $product['name'],
        'image' => $images,
        'description' => $description,
        'category' => (string) ($product['category_name'] ?? ''),
        'brand' => [
            '@type' => 'Brand',
            'name' => 'Noor Handmade',
        ],
        'offers' => [
            '@type' => 'Offer',
            'url' => $canonical_url,
            'priceCurrency' => 'EGP',
            'price' => number_format((float) $product['price'], 2, '.', ''),
            'availability' => (int) $product['stock_quantity'] > 0
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => [
                '@id' => seo_absolute_url() . '#organization',
            ],
        ],
    ];

    return $schema;
}

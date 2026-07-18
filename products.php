<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once 'includes/db_connection.php';


$price_bounds = $pdo->query("SELECT MIN(price) as min, MAX(price) as max FROM products")->fetch(PDO::FETCH_ASSOC);

$min_price_limit = (float) ($price_bounds['min'] ?? 0);
$max_price_limit = (float) ($price_bounds['max'] ?? 1000);


$category_slug = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
if ($category_slug === '') {
    $category_slug = null;
}

$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float) $_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float) $_GET['max_price'] : null;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_category = null;
if ($category_slug !== null) {
    $category_stmt = $pdo->prepare("SELECT id, name, slug FROM categories WHERE slug = ? LIMIT 1");
    $category_stmt->execute([$category_slug]);
    $selected_category = $category_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$selected_category) {
        http_response_code(404);
    }
}


$sql = "SELECT p.* FROM products p";
$params = [];
$where_clauses = [];

if ($selected_category) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $selected_category['id'];
} elseif ($category_slug !== null) {
    $where_clauses[] = "1 = 0";
}


if ($min_price !== null) {
    $where_clauses[] = "p.price >= ?";
    $params[] = $min_price;
}
if ($max_price !== null) {
    $where_clauses[] = "p.price <= ?";
    $params[] = $max_price;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$count_sql = preg_replace('/^SELECT p\.\* FROM products p/', 'SELECT COUNT(*) FROM products p', $sql);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = (int) $count_stmt->fetchColumn();
$per_page = 12;
$total_pages = max(1, (int) ceil($total_products / $per_page));
$current_page = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$offset = ($current_page - 1) * $per_page;

switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}
$sql .= " LIMIT {$per_page} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);


$product_images = [];
if (!empty($products)) {
    $product_ids = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $img_sql = "SELECT product_id, image_name FROM product_images WHERE product_id IN ($placeholders) ORDER BY id ASC";
    $img_stmt = $pdo->prepare($img_sql);
    $img_stmt->execute($product_ids);
    $images_result = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($images_result as $img) {
        $product_images[$img['product_id']][] = $img['image_name'];
    }
}

$current_category_name = "كل المنتجات";
if ($selected_category) {
    $current_category_name = $selected_category['name'];
} elseif ($category_slug !== null) {
    $current_category_name = "القسم غير موجود";
}

$page_title = $selected_category
    ? $selected_category['name'] . ' | Noor Handmade'
    : ($category_slug !== null ? 'القسم غير موجود | Noor Handmade' : 'كل المنتجات اليدوية | Noor Handmade');
$page_description = $selected_category
    ? 'تصفح منتجات قسم ' . $selected_category['name'] . ' من Noor Handmade واختر من قطع يدوية مميزة مصنوعة بعناية.'
    : ($category_slug !== null
        ? 'القسم الذي تبحث عنه غير موجود. تصفح أقسام ومنتجات Noor Handmade المتاحة.'
        : 'تصفح جميع منتجات Noor Handmade اليدوية واكتشف قطعًا مميزة مصنوعة بعناية لتناسب ذوقك.');
$page_canonical_path = 'products.php';
if ($selected_category) {
    $page_canonical_path .= '?category=' . rawurlencode($selected_category['slug']);
} elseif ($category_slug !== null) {
    $page_canonical_path .= '?category=' . rawurlencode($category_slug);
}

$has_seo_filter = $min_price !== null || $max_price !== null || $sort_by !== 'newest';
if ($category_slug !== null && !$selected_category) {
    $page_robots = 'noindex, follow';
} elseif ($has_seo_filter) {
    $page_robots = 'noindex, follow';
} elseif ($current_page > 1) {
    $page_canonical_path .= ($selected_category ? '&' : '?') . 'page=' . $current_page;
}

require_once __DIR__ . '/includes/seo.php';
$products_breadcrumb_items = [
    ['name' => 'الرئيسية', 'url' => ''],
    ['name' => 'المنتجات', 'url' => 'products.php'],
];
if ($selected_category) {
    $products_breadcrumb_items[] = [
        'name' => $selected_category['name'],
        'url' => 'products.php?category=' . rawurlencode($selected_category['slug']),
    ];
}
if ($current_page > 1 && !$has_seo_filter) {
    $products_breadcrumb_items[] = [
        'name' => 'صفحة ' . $current_page,
        'url' => $page_canonical_path,
    ];
}
if (!isset($page_robots) || stripos($page_robots, 'noindex') === false) {
    $page_structured_data = [seo_breadcrumb_schema($products_breadcrumb_items)];
}
?>


<?php require_once 'includes/header.php'; ?>
<div style="height: 50px;"></div>
<style>
    /* Unchanged styles from before */


    .product-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        text-decoration: none;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .product-card-body {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .product-title {
        font-family: var(--font-headings);
        font-size: 1.3rem;
    }

    .product-title a {
        color: var(--dark-color);
        text-decoration: none;
    }

    .product-price {
        color: var(--primary-color);
        font-size: 1.2rem;
        font-weight: 700;
        margin-top: auto;
    }

    .btn-details {
        background-color: var(--primary-color);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 10px;
        font-weight: 700;
        width: 100%;
        transition: background-color 0.3s ease;
        text-decoration: none;
        display: block;
        text-align: center;
    }

    .btn-details:hover {
        background-color: var(--dark-color);
        color: #fff;
    }

    .filter-section {
        margin-bottom: 2rem;
    }

    .sort-bar {
        margin-bottom: 1.5rem;
    }

    .product-card-slider {
        width: 100%;
        height: 250px;
        position: relative;
    }

    .product-card-slider .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-card-slider .swiper-button-next,
    .product-card-slider .swiper-button-prev {
        color: #fff;
        background-color: rgba(0, 0, 0, 0.3);
        width: 30px;
        height: 30px;
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .product-card:hover .swiper-button-next,
    .product-card:hover .swiper-button-prev {
        opacity: 1;
    }

    .product-card-slider .swiper-button-next:after,
    .product-card-slider .swiper-button-prev:after {
        font-size: 14px;
        font-weight: 900;
    }

    /* --- NEW STYLES --- */
    /* Price Slider (noUiSlider) Customization */
    .price-slider-container {
        padding: 10px;
    }

    .price-slider-display {
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 20px;
        text-align: center;
    }

    #price-slider {
        margin: 0 10px;
    }

    .noUi-target {
        border-radius: 8px;
        border: 1px solid #ddd;
        background: #f8f8f8;
        box-shadow: none;
    }

    .noUi-connect {
        background: var(--primary-color);
    }

    .noUi-handle {
        border: 2px solid #fff;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .noUi-handle:focus {
        outline: none;
    }

    .noUi-handle:before,
    .noUi-handle:after {
        display: none;
    }

    /* Mobile Filter Button */
    .btn-mobile-filter {
        background-color: var(--primary-color);
        color: #fff;
        font-weight: 700;
    }
</style>
<div class="container products-page">
    <nav class="seo-breadcrumb" aria-label="مسار التنقل">
        <ol>
            <li><a href="index.php">الرئيسية</a></li>
            <?php if ($selected_category): ?>
                <li><a href="products.php">المنتجات</a></li>
                <?php if ($current_page > 1 && !$has_seo_filter): ?>
                    <li>
                        <a href="products.php?category=<?= urlencode($selected_category['slug']) ?>">
                            <?= htmlspecialchars($selected_category['name']) ?>
                        </a>
                    </li>
                    <li aria-current="page">صفحة <?= $current_page ?></li>
                <?php else: ?>
                    <li aria-current="page"><?= htmlspecialchars($selected_category['name']) ?></li>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($current_page > 1 && !$has_seo_filter): ?>
                    <li><a href="products.php">المنتجات</a></li>
                    <li aria-current="page">صفحة <?= $current_page ?></li>
                <?php else: ?>
                    <li aria-current="page">المنتجات</li>
                <?php endif; ?>
            <?php endif; ?>
        </ol>
    </nav>
    <div class="row">
        <div class="col-lg-3">
    <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="filterSidebar"
        aria-labelledby="filterSidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="filterSidebarLabel">تصفية المنتجات</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filterSidebar"
                aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form action="products.php" method="GET" id="filter-form" class="filter-sidebar">
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">

                <div class="filter-section">
                    <h4>الأقسام</h4>
                    <div class="list-group">
                        <a href="products.php?sort_by=<?= htmlspecialchars($sort_by) ?>"
                            class="list-group-item list-group-item-action <?= $category_slug === null ? 'active' : '' ?>">كل المنتجات</a>
                        <?php foreach ($categories as $category): ?>
                            <a href="products.php?category=<?= urlencode($category['slug']) ?>&sort_by=<?= urlencode($sort_by) ?>"
                                class="list-group-item list-group-item-action <?= ($category_slug === $category['slug']) ? 'active' : '' ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-section">
                    <h4>السعر</h4>
                    <div class="price-slider-container">
                        <div id="price-slider-display" class="price-slider-display"></div>
                        <div id="price-slider"></div>
                        <input type="hidden" name="min_price" id="min-price-input" value="<?= htmlspecialchars($min_price ?? '') ?>">
                        <input type="hidden" name="max_price" id="max-price-input" value="<?= htmlspecialchars($max_price ?? '') ?>">
                        <?php if ($category_slug !== null): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($category_slug) ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="filter-section">
                    <h4>نطاقات الأسعار</h4>
                    <div class="list-group">
                        <?php
                        
                        $price_presets = [
                            ['label' => 'أقل من 100 جنيه', 'min' => null, 'max' => 100],
                            ['label' => '100 - 300 جنيه', 'min' => 100, 'max' => 300],
                            ['label' => '300 - 500 جنيه', 'min' => 300, 'max' => 500],
                            ['label' => 'أكبر من 500 جنيه', 'min' => 500, 'max' => null]
                        ];

                        
                        $base_params = [];
                        if ($category_slug !== null) {
                            $base_params['category'] = $category_slug;
                        }
                        $base_params['sort_by'] = $sort_by;

                        foreach ($price_presets as $preset) {
                            $preset_params = $base_params;
                            if ($preset['min'] !== null) {
                                $preset_params['min_price'] = $preset['min'];
                            }
                            if ($preset['max'] !== null) {
                                $preset_params['max_price'] = $preset['max'];
                            }
                            
                            
                            $is_active = (($min_price == $preset['min']) && ($max_price == $preset['max']));
                            $active_class = $is_active ? 'active' : '';

                            $url = 'products.php?' . http_build_query($preset_params);
                            echo "<a href='{$url}' class='list-group-item list-group-item-action {$active_class}'>{$preset['label']}</a>";
                        }
                        ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">تطبيق الفلتر</button>
            </form>
        </div>
    </div>
</div>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="mb-4">
                    <h1 class="mb-1 fs-2"><?= htmlspecialchars($current_category_name) ?></h1>
                    <p class="mb-0 text-muted"><?= htmlspecialchars($page_description) ?></p>
                </div>
                <button class="btn btn-mobile-filter d-lg-none mb-4" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#filterSidebar" aria-controls="filterSidebar">
                    ☰ تصفية المنتجات
                </button>
                <form action="products.php" method="GET" class="sort-bar d-none d-lg-flex align-items-center">
                    <?php if ($category_slug !== null): ?><input type="hidden" name="category"
                            value="<?= htmlspecialchars($category_slug) ?>"><?php endif; ?>
                    <?php if ($min_price !== null): ?><input type="hidden" name="min_price"
                            value="<?= htmlspecialchars($min_price) ?>"><?php endif; ?>
                    <?php if ($max_price !== null): ?><input type="hidden" name="max_price"
                            value="<?= htmlspecialchars($max_price) ?>"><?php endif; ?>
                    <label for="sort_by" class="form-label me-2 mb-0">ترتيب حسب:</label>
                    <select name="sort_by" id="sort_by" class="form-select" onchange="this.form.submit()">
                        <option value="newest" <?= ($sort_by == 'newest') ? 'selected' : '' ?>>الأحدث</option>
                        <option value="price_asc" <?= ($sort_by == 'price_asc') ? 'selected' : '' ?>>السعر: من الأقل للأعلى
                        </option>
                        <option value="price_desc" <?= ($sort_by == 'price_desc') ? 'selected' : '' ?>>السعر: من الأعلى
                            للأقل</option>
                    </select>
                </form>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php if (empty($products)): ?>
                    <div class="col-12">
                        <p class="text-center text-muted fs-4 mt-5">لا توجد منتجات تطابق معايير البحث.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col">
                            <div class="product-card">
                                <div class="swiper product-card-slider">
                                    <div class="swiper-wrapper">
                                        <?php
                                        $images = $product_images[$product['id']] ?? [];
                                        if (!empty($images)):
                                            foreach ($images as $image): ?>
                                                <div class="swiper-slide">
                                                    <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>"><img
                                                            src="images/products/<?= htmlspecialchars($image) ?>"
                                                            alt="<?= htmlspecialchars($product['name']) ?>"
                                                            width="800" height="1000" loading="lazy" decoding="async"></a>
                                                </div>
                                            <?php endforeach; else: ?>
                                            <div class="swiper-slide">
                                                <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>"><img
                                                        src="images/products/placeholder.svg"
                                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                                        width="800" height="1000" loading="lazy" decoding="async"></a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="swiper-button-next"></div>
                                    <div class="swiper-button-prev"></div>
                                </div>
                                <div class="product-card-body">
    <h3 class="product-title"><a
            href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>"><?= htmlspecialchars($product['name']) ?></a>
    </h3>
    <p class="product-price mt-3"><?= htmlspecialchars($product['price']) ?> جنيه</p>
    <?php if ((int) $product['stock_quantity'] < 1): ?>
        <span class="badge bg-danger mb-2">نفد من المخزون</span>
    <?php elseif ((int) $product['stock_quantity'] <= 5): ?>
        <span class="badge bg-warning text-dark mb-2">متبقي <?= (int) $product['stock_quantity'] ?> فقط</span>
    <?php endif; ?>
    <div class="d-flex mt-3 gap-2">
        <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>"
            class="btn btn-details flex-grow-1"><i class="fas fa-eye me-1"></i> التفاصيل</a>
        <button class="btn btn-primary-custom add-to-cart-btn" data-product-id="<?= $product['id'] ?>" <?= (int) $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>
            <i class="fas fa-cart-plus"></i>
        </button>
    </div>
</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <nav class="mt-5" aria-label="صفحات المنتجات">
                    <ul class="pagination justify-content-center">
                        <?php for ($page_number = 1; $page_number <= $total_pages; $page_number++):
                            $page_params = $_GET;
                            $page_params['page'] = $page_number;
                        ?>
                            <li class="page-item <?= $page_number === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="products.php?<?= htmlspecialchars(http_build_query($page_params), ENT_QUOTES, 'UTF-8') ?>"><?= $page_number ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<div style="height: 80px !important;"></div>
<!-- <div style="position: fixed; bottom: 0; width: 100%; z-index: 1000;">
    
</div> -->

<?php require_once 'includes/footer.php'; ?>

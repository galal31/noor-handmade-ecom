<?php

require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/db_connection.php';

$important_products_query = "
    SELECT p.*,
           c.name AS category_name,
           COALESCE(
               p.main_image,
               (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1),
               'placeholder.svg'
           ) AS display_image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_important = TRUE AND p.stock_quantity > 0
    ORDER BY p.id DESC
";
$important_products = $pdo->query($important_products_query)->fetchAll(PDO::FETCH_ASSOC);
$featured_product = $important_products[0] ?? null;
$editorial_products = array_slice($important_products, 1, 3);

$latest_products_query = "
    SELECT p.*,
           c.name AS category_name,
           COALESCE(
               p.main_image,
               (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1),
               'placeholder.svg'
           ) AS display_image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_important = FALSE AND p.stock_quantity > 0
    ORDER BY p.created_at DESC
    LIMIT 8
";
$latest_products = $pdo->query($latest_products_query)->fetchAll(PDO::FETCH_ASSOC);

$categories_query = "
    SELECT id,
           name,
           slug,
           COALESCE(NULLIF(image, ''), 'placeholder.svg') AS display_image
    FROM categories
    ORDER BY name ASC
";
$categories = $pdo->query($categories_query)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Noor Handmade | قطع يدوية مصنوعة بحب';
$page_description = 'اكتشف قطعًا يدوية مميزة من Noor Handmade، مصنوعة بعناية لتضيف لمسة دافئة وشخصية إلى يومك.';
$page_canonical_path = '';
$page_image = $featured_product
    ? 'images/products/' . $featured_product['display_image']
    : 'images/logo.jpeg';
$page_image_alt = $featured_product
    ? $featured_product['name']
    : 'Noor Handmade للمنتجات اليدوية';
$page_preload_image = $page_image;

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .home-editorial {
        --home-ink: #263734;
        --home-muted: #68736f;
        --home-teal: #168c78;
        --home-teal-dark: #0f6f60;
        --home-orange: #d96f20;
        --home-cream: #fffaf3;
        --home-paper: #fffdf9;
        --home-line: #e8e2d9;
        background: var(--home-paper);
        color: var(--home-ink);
        overflow: hidden;
    }

    .home-editorial h1,
    .home-editorial h2,
    .home-editorial h3 {
        color: var(--home-ink);
        font-family: 'El Messiri', serif;
        font-weight: 700;
    }

    .home-editorial a {
        color: inherit;
    }

    .home-section {
        padding: 96px 0;
    }

    .home-section-heading {
        display: flex;
        gap: 24px;
        align-items: end;
        justify-content: space-between;
        margin-bottom: 42px;
    }

    .home-section-heading h2 {
        max-width: 620px;
        margin: 0;
        font-size: clamp(2rem, 4vw, 3.25rem);
        line-height: 1.25;
    }

    .home-section-heading p {
        max-width: 470px;
        margin: 0;
        color: var(--home-muted);
        line-height: 1.9;
    }

    .home-text-link {
        position: relative;
        display: inline-block;
        padding-bottom: 5px;
        color: var(--home-teal) !important;
        font-weight: 700;
        text-decoration: none;
    }

    .home-text-link::after {
        content: '';
        position: absolute;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 1px;
        background: currentColor;
        transform-origin: right;
        transition: transform 0.25s ease;
    }

    .home-text-link:hover::after {
        transform: scaleX(0.45);
    }

    .home-hero {
        padding: 48px 0 72px;
        background: var(--home-cream);
    }

    .home-hero-grid {
        display: grid;
        grid-template-columns: minmax(0, 0.9fr) minmax(420px, 1.1fr);
        gap: clamp(42px, 7vw, 100px);
        align-items: center;
        min-height: 610px;
    }

    .home-kicker {
        display: block;
        margin-bottom: 20px;
        color: var(--home-teal);
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 0.12em;
    }

    .home-hero h1 {
        margin: 0 0 24px;
        font-size: clamp(3rem, 6vw, 5.6rem);
        line-height: 1.12;
    }

    .home-hero-copy {
        max-width: 590px;
        margin: 0 0 34px;
        color: var(--home-muted);
        font-size: 1.08rem;
        line-height: 2;
    }

    .home-hero-actions {
        display: flex;
        gap: 24px;
        align-items: center;
        flex-wrap: wrap;
    }

    .home-primary-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 52px;
        padding: 12px 32px;
        border: 1px solid var(--home-orange);
        border-radius: 4px;
        background: var(--home-orange);
        color: #fff !important;
        font-weight: 800;
        text-decoration: none;
        transition: background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
    }

    .home-primary-action:hover {
        border-color: #b95b18;
        background: #b95b18;
        transform: translateY(-2px);
    }

    .home-hero-media {
        position: relative;
        min-height: 610px;
    }

    .home-hero-image-link {
        display: block;
        height: 610px;
        overflow: hidden;
        background: #eee8df;
    }

    .home-hero-image-link img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.7s ease;
    }

    .home-hero-image-link:hover img {
        transform: scale(1.025);
    }

    .home-featured-note {
        position: absolute;
        right: -38px;
        bottom: 36px;
        width: min(310px, calc(100% - 30px));
        padding: 20px 22px;
        border: 1px solid var(--home-line);
        background: #fff;
    }

    .home-featured-note small {
        display: block;
        margin-bottom: 7px;
        color: var(--home-muted);
    }

    .home-featured-note strong {
        display: block;
        font-size: 1.08rem;
    }

    .home-featured-note span {
        display: block;
        margin-top: 8px;
        color: var(--home-teal);
        font-weight: 800;
    }

    .home-values {
        border-top: 1px solid var(--home-line);
        border-bottom: 1px solid var(--home-line);
        background: #fff;
    }

    .home-values-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
    }

    .home-value {
        padding: 28px 20px;
        text-align: center;
        font-family: 'El Messiri', serif;
        font-size: 1.05rem;
        font-weight: 700;
    }

    .home-value + .home-value {
        border-right: 1px solid var(--home-line);
    }

    .home-categories {
        background: #fff;
    }

    .home-category-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 34px 24px;
    }

    .home-category-card {
        display: block;
        text-decoration: none;
    }

    .home-category-image {
        aspect-ratio: 4 / 5;
        overflow: hidden;
        background: #f0ece6;
    }

    .home-category-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.55s ease;
    }

    .home-category-card:hover .home-category-image img {
        transform: scale(1.035);
    }

    .home-category-name {
        display: inline-block;
        margin-top: 16px;
        padding-bottom: 4px;
        border-bottom: 1px solid transparent;
        font-size: 1.22rem;
        font-weight: 800;
        transition: border-color 0.25s ease, color 0.25s ease;
    }

    .home-category-card:hover .home-category-name {
        border-color: currentColor;
        color: var(--home-teal);
    }

    .home-curated {
        background: var(--home-cream);
    }

    .home-curated-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
    }

    .home-curated-card {
        display: block;
        text-decoration: none;
    }

    .home-curated-image {
        aspect-ratio: 3 / 4;
        overflow: hidden;
        background: #eee8df;
    }

    .home-curated-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.55s ease;
    }

    .home-curated-card:hover img {
        transform: scale(1.035);
    }

    .home-curated-meta {
        display: flex;
        gap: 18px;
        align-items: start;
        justify-content: space-between;
        padding-top: 18px;
    }

    .home-curated-meta h3 {
        margin: 0;
        font-size: 1.28rem;
    }

    .home-curated-meta span {
        flex: 0 0 auto;
        color: var(--home-teal);
        font-weight: 800;
    }

    .home-products {
        background: #fff;
    }

    .home-products-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 42px 22px;
    }

    .home-product-card {
        min-width: 0;
    }

    .home-product-image {
        display: block;
        aspect-ratio: 4 / 5;
        overflow: hidden;
        background: #f0ece6;
    }

    .home-product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.55s ease;
    }

    .home-product-card:hover .home-product-image img {
        transform: scale(1.035);
    }

    .home-product-info {
        padding-top: 16px;
    }

    .home-product-category {
        min-height: 20px;
        margin-bottom: 7px;
        color: var(--home-muted);
        font-size: 0.78rem;
    }

    .home-product-title {
        margin: 0 0 9px;
        font-size: 1.07rem;
        line-height: 1.55;
    }

    .home-product-title a {
        text-decoration: none;
    }

    .home-product-title a:hover {
        color: var(--home-teal);
    }

    .home-product-price {
        margin: 0 0 15px;
        color: var(--home-teal-dark);
        font-weight: 800;
    }

    .home-add-cart {
        width: 100%;
        min-height: 44px;
        border: 1px solid var(--home-ink);
        border-radius: 3px;
        background: transparent;
        color: var(--home-ink);
        font-weight: 800;
        transition: background 0.25s ease, color 0.25s ease;
    }

    .home-add-cart:hover {
        background: var(--home-ink);
        color: #fff;
    }

    .home-story {
        padding: 108px 0;
        background: var(--home-ink);
        color: #fff;
    }

    .home-story-grid {
        display: grid;
        grid-template-columns: minmax(320px, 0.85fr) minmax(0, 1.15fr);
        gap: clamp(50px, 8vw, 120px);
        align-items: center;
    }

    .home-story-image {
        aspect-ratio: 4 / 5;
        overflow: hidden;
        background: #374945;
    }

    .home-story-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .home-story h2 {
        max-width: 650px;
        margin: 0 0 24px;
        color: #fff;
        font-size: clamp(2.3rem, 5vw, 4.5rem);
        line-height: 1.25;
    }

    .home-story p {
        max-width: 620px;
        margin: 0 0 28px;
        color: #dce4e1;
        font-size: 1.02rem;
        line-height: 2.05;
    }

    .home-story .home-text-link {
        color: #fff !important;
    }

    .home-track {
        padding: 72px 0;
        border-bottom: 1px solid var(--home-line);
        background: var(--home-cream);
        text-align: center;
    }

    .home-track h2 {
        margin: 0 0 12px;
        font-size: clamp(2rem, 4vw, 3rem);
    }

    .home-track p {
        margin: 0 0 24px;
        color: var(--home-muted);
        line-height: 1.9;
    }

    @media (max-width: 991.98px) {
        .home-section {
            padding: 76px 0;
        }

        .home-hero-grid {
            grid-template-columns: 1fr 1fr;
            gap: 38px;
            min-height: 520px;
        }

        .home-hero h1 {
            font-size: clamp(2.7rem, 7vw, 4.2rem);
        }

        .home-hero-media,
        .home-hero-image-link {
            min-height: 520px;
            height: 520px;
        }

        .home-featured-note {
            right: -18px;
        }

        .home-products-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .home-section {
            padding: 62px 0;
        }

        .home-section-heading {
            display: block;
            margin-bottom: 30px;
        }

        .home-section-heading p,
        .home-section-heading .home-text-link {
            margin-top: 14px;
        }

        .home-hero {
            padding: 26px 0 54px;
        }

        .home-hero-grid {
            grid-template-columns: 1fr;
            gap: 38px;
            min-height: 0;
        }

        .home-hero-content {
            padding-top: 24px;
        }

        .home-hero h1 {
            font-size: clamp(2.7rem, 13vw, 4rem);
        }

        .home-hero-copy {
            font-size: 1rem;
        }

        .home-hero-media,
        .home-hero-image-link {
            min-height: 0;
            height: auto;
        }

        .home-hero-image-link {
            aspect-ratio: 4 / 5;
        }

        .home-featured-note {
            right: 16px;
            bottom: 16px;
        }

        .home-values-row {
            grid-template-columns: 1fr;
        }

        .home-value + .home-value {
            border-top: 1px solid var(--home-line);
            border-right: 0;
        }

        .home-category-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px 14px;
        }

        .home-curated-grid {
            grid-template-columns: 1fr;
            gap: 40px;
        }

        .home-curated-image {
            aspect-ratio: 4 / 5;
        }

        .home-story {
            padding: 72px 0;
        }

        .home-story-grid {
            grid-template-columns: 1fr;
            gap: 42px;
        }

        .home-story-image {
            max-height: 520px;
        }
    }

    @media (max-width: 479.98px) {
        .home-products-grid {
            gap: 34px 12px;
        }

        .home-product-info {
            padding-top: 12px;
        }

        .home-product-title {
            font-size: 0.98rem;
        }

        .home-add-cart {
            padding-inline: 8px;
            font-size: 0.88rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .home-editorial *,
        .home-editorial *::before,
        .home-editorial *::after {
            scroll-behavior: auto !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>

<main class="home-editorial">
    <section class="home-hero">
        <div class="container">
            <div class="home-hero-grid">
                <div class="home-hero-content">
                    <span class="home-kicker">NOOR HANDMADE</span>
                    <h1>قطع يدوية تحمل روح التفاصيل</h1>
                    <p class="home-hero-copy">
                        اختيارات مصنوعة بعناية لتضيف لمسة شخصية ودافئة إلى يومك، وكل قطعة منها تحمل طابعًا لا يتكرر.
                    </p>
                    <div class="home-hero-actions">
                        <a href="products.php" class="home-primary-action">تسوق الآن</a>
                        <?php if (!empty($categories)): ?>
                            <a href="#home-categories" class="home-text-link">تصفح الأقسام</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="home-hero-media">
                    <?php if ($featured_product): ?>
                        <a class="home-hero-image-link" href="product_details.php?slug=<?= htmlspecialchars($featured_product['slug']) ?>">
                            <img src="images/products/<?= htmlspecialchars($featured_product['display_image']) ?>"
                                 alt="<?= htmlspecialchars($featured_product['name']) ?>"
                                 width="1000" height="1250" decoding="async" fetchpriority="high">
                        </a>
                        <div class="home-featured-note">
                            <small>اختيار مميز</small>
                            <strong><?= htmlspecialchars($featured_product['name']) ?></strong>
                            <span><?= number_format((float) $featured_product['price'], 2) ?> جنيه</span>
                        </div>
                    <?php else: ?>
                        <a class="home-hero-image-link" href="products.php">
                            <img src="images/products/placeholder.svg" alt="تصفح منتجات Noor Handmade"
                                 width="1000" height="1250" decoding="async" fetchpriority="high">
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="home-values" aria-label="مميزات Noor Handmade">
        <div class="container">
            <div class="home-values-row">
                <div class="home-value">صناعة يدوية بعناية</div>
                <div class="home-value">خامات مختارة</div>
                <div class="home-value">قطع محدودة ومميزة</div>
            </div>
        </div>
    </section>

    <?php if (!empty($categories)): ?>
        <section class="home-section home-categories" id="home-categories">
            <div class="container">
                <div class="home-section-heading">
                    <h2>تصفح حسب ما يناسب ذوقك</h2>
                    <p>كل قسم يجمع قطعًا لها طابعها الخاص، من التفاصيل البسيطة إلى القطع التي تلفت النظر.</p>
                </div>

                <div class="home-category-grid">
                    <?php foreach ($categories as $category): ?>
                        <a href="products.php?category=<?= urlencode($category['slug']) ?>" class="home-category-card">
                            <div class="home-category-image">
                                <img src="images/categories/<?= htmlspecialchars($category['display_image']) ?>"
                                     alt="<?= htmlspecialchars($category['name']) ?>"
                                     width="800" height="1000" loading="lazy" decoding="async">
                            </div>
                            <span class="home-category-name"><?= htmlspecialchars($category['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($editorial_products)): ?>
        <section class="home-section home-curated">
            <div class="container">
                <div class="home-section-heading">
                    <h2>اختيارات Noor</h2>
                    <a href="products.php" class="home-text-link">مشاهدة كل المنتجات</a>
                </div>

                <div class="home-curated-grid">
                    <?php foreach ($editorial_products as $product): ?>
                        <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>" class="home-curated-card">
                            <div class="home-curated-image">
                                <img src="images/products/<?= htmlspecialchars($product['display_image']) ?>"
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     width="800" height="1000" loading="lazy" decoding="async">
                            </div>
                            <div class="home-curated-meta">
                                <h3><?= htmlspecialchars($product['name']) ?></h3>
                                <span><?= number_format((float) $product['price'], 2) ?> جنيه</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($latest_products)): ?>
        <section class="home-section home-products">
            <div class="container">
                <div class="home-section-heading">
                    <h2>وصل حديثًا</h2>
                    <p>أحدث القطع التي أضفناها إلى مجموعتنا، متاحة الآن بأعداد محدودة.</p>
                </div>

                <div class="home-products-grid">
                    <?php foreach ($latest_products as $product): ?>
                        <article class="home-product-card">
                            <a class="home-product-image" href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>">
                                <img src="images/products/<?= htmlspecialchars($product['display_image']) ?>"
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     width="800" height="1000" loading="lazy" decoding="async">
                            </a>
                            <div class="home-product-info">
                                <div class="home-product-category"><?= htmlspecialchars($product['category_name'] ?? '') ?></div>
                                <h3 class="home-product-title">
                                    <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h3>
                                <p class="home-product-price"><?= number_format((float) $product['price'], 2) ?> جنيه</p>
                                <button type="button"
                                        class="home-add-cart add-to-cart-btn"
                                        data-product-id="<?= (int) $product['id'] ?>">
                                    أضف إلى السلة
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="home-story">
        <div class="container">
            <div class="home-story-grid">
                <div class="home-story-image">
                    <?php if ($featured_product): ?>
                        <img src="images/products/<?= htmlspecialchars($featured_product['display_image']) ?>"
                             alt="تفاصيل منتجات Noor Handmade"
                             width="800" height="1000" loading="lazy" decoding="async">
                    <?php else: ?>
                        <img src="images/logo.jpeg" alt="Noor Handmade"
                             width="800" height="1000" loading="lazy" decoding="async">
                    <?php endif; ?>
                </div>
                <div>
                    <span class="home-kicker">قصتنا مع التفاصيل</span>
                    <h2>الجمال يبدأ من قطعة لها شخصية</h2>
                    <p>
                        نختار منتجاتنا لأننا نؤمن أن العمل اليدوي ليس مجرد شكل جميل؛ بل وقت وعناية وتفاصيل تمنح كل قطعة حضورها الخاص.
                        هدفنا أن تصل إليك قطعة تحبها وتظل مرتبطة بذكرى جميلة.
                    </p>
                    <a href="products.php" class="home-text-link">اكتشف المجموعة</a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-track">
        <div class="container">
            <h2>تبحث عن طلب سابق؟</h2>
            <p>استخدم رقم الطلب والبريد الإلكتروني لمعرفة آخر تحديث لحالة طلبك.</p>
            <a href="track_order.php" class="home-primary-action">تتبع طلبك</a>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

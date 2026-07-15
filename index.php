<?php



require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once("includes/db_connection.php");


$important_products_query = "
    SELECT p.*, 
    COALESCE(p.main_image, (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1), 'placeholder.svg') AS display_image
    FROM products p
    WHERE p.is_important = TRUE AND p.stock_quantity > 0
    ORDER BY p.id DESC
";
$important_products = $pdo->query($important_products_query)->fetchAll(PDO::FETCH_ASSOC);


$latest_products_query = "
    SELECT p.*, 
    COALESCE(p.main_image, (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1), 'placeholder.svg') AS display_image
    FROM products p
    WHERE p.is_important = FALSE AND p.stock_quantity > 0
    ORDER BY p.created_at DESC LIMIT 4
";
$latest_products = $pdo->query($latest_products_query)->fetchAll(PDO::FETCH_ASSOC);


$categories = $pdo->query("SELECT id, name, slug, image FROM categories WHERE image IS NOT NULL AND image != '' ORDER BY name ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Noor Handmade | عالم من الإبداع اليدوي';

?>
<?php require_once 'includes/header.php'; ?>

    <style>
        

        :root {
            --primary-color: #16a085; 
            --secondary-color: #e67e22; 
            --dark-color: #2c3e50; 
            --light-color: #ecf0f1; 
            --font-headings: 'El Messiri', serif;
            --font-body: 'Cairo', sans-serif;
        }

        body {
            font-family: var(--font-body);
            color: #333;
            background-color: #fff;
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand {
            font-family: var(--font-headings);
            font-weight: 700;
        }

        .section {
            padding: 80px 0;
        }

        .section-title {
            font-size: 3rem;
            color: var(--dark-color);
            margin-bottom: 50px;
            position: relative;
            padding-bottom: 20px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 50%;
            transform: translateX(50%);
            width: 100px;
            height: 5px;
            background-image: linear-gradient(to left, var(--primary-color), var(--secondary-color));
            border-radius: 5px;
        }
        
        

        .navbar {
            background-color: #fff !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 1rem 0;
        }
        .navbar-brand {
            color: var(--primary-color) !important;
            font-size: 1.8rem;
        }
        .nav-link {
            color: var(--dark-color) !important;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0 0.8rem;
            transition: color 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--primary-color) !important;
        }
        .btn-cart {
            background-color: var(--secondary-color);
            color: #fff;
            border-radius: 50px;
            font-weight: 700;
            padding: 0.6rem 1.5rem;
            transition: all 0.3s ease;
        }
        .btn-cart:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(230, 126, 34, 0.4);
        }

        

        .hero-swiper {
            width: 100%;
            height: 90vh;
        }
        .hero-swiper .swiper-slide {
            text-align: center;
            font-size: 18px;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            background-size: cover;
            background-position: center;
            color: #fff;
        }
        .hero-swiper .slide-content {
            z-index: 10;
            max-width: 800px;
            padding: 2.5rem;
            background: rgba(44, 62, 80, 0.7); 
            backdrop-filter: blur(5px);
            border-radius: 15px;
        }
        .hero-swiper .hero-title {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .hero-swiper .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .hero-swiper .btn-hero {
            background-color: var(--primary-color);
            border: 2px solid var(--primary-color);
            color: #fff;
            padding: 12px 40px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 50px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .hero-swiper .btn-hero:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: scale(1.05);
        }
        
        .hero-swiper .swiper-button-next,
        .hero-swiper .swiper-button-prev {
            color: #fff;
            background-color: rgba(0, 0, 0, 0.2);
            width: 50px;
            height: 50px;
            border-radius: 50%;
        }
        .hero-swiper .swiper-button-next:after,
        .hero-swiper .swiper-button-prev:after {
            font-size: 24px;
        }
        .hero-swiper .swiper-pagination-bullet {
            background: #fff;
            width: 12px;
            height: 12px;
            opacity: 0.7;
        }
        .hero-swiper .swiper-pagination-bullet-active {
            background: var(--primary-color);
            opacity: 1;
        }

        
 
        .feature-box {
            padding: 30px;
            background: #fff;
            text-align: center;
        }
        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .feature-box h3 {
            color: var(--dark-color);
        }

        

        .product-card, .category-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
        }
        .product-card:hover, .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        .category-card { position: relative; }
        .category-card img { height: 400px; width: 100%; object-fit: cover; }
        .category-card .overlay-text {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(255,255,255,0.9);
            padding: 10px 20px;
            border-radius: 10px;
            color: var(--dark-color);
            font-size: 1.5rem;
            font-weight: 700;
        }
        .product-card-body { padding: 20px; }
        .product-title {
            font-size: 1.4rem;
            color: var(--dark-color);
        }
        .product-price {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .btn-product {
            background-color: var(--primary-color);
            color: #fff;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-product:hover {
            background-color: var(--dark-color);
            color: #fff;
            transform: scale(1.05);
        }

        

        .footer {
            background-color: var(--dark-color);
            color: #fff;
            padding: 40px 0;
        }

        @media (max-width: 767.98px) {
            .hero-swiper {
                height: clamp(520px, 68vh, 640px);
            }

            .hero-swiper .slide-content {
                max-width: calc(100% - 32px);
                padding: 1.6rem 1.2rem;
            }

            .hero-swiper .hero-title {
                font-size: clamp(2.15rem, 12vw, 3rem);
                line-height: 1.15;
            }

            .hero-swiper .hero-subtitle {
                margin-bottom: 1.5rem;
                font-size: 1rem;
                line-height: 1.7;
            }

            .hero-swiper .btn-hero {
                padding: 10px 28px;
                font-size: 1rem;
            }

            .hero-swiper .swiper-button-next,
            .hero-swiper .swiper-button-prev {
                width: 42px;
                height: 42px;
            }
        }
    </style>
<main>
    <?php if (!empty($important_products)): ?>
    <section class="hero-section">
        <div class="swiper hero-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($important_products as $heroIndex => $product): ?>
                <div class="swiper-slide" style="background-image: url('images/products/<?= htmlspecialchars($product['display_image']) ?>')">
                    <div class="slide-content">
                        <?php if ($heroIndex === 0): ?>
                            <h1 class="hero-title"><?= htmlspecialchars($product['name']) ?></h1>
                        <?php else: ?>
                            <h2 class="hero-title"><?= htmlspecialchars($product['name']) ?></h2>
                        <?php endif; ?>
                        <?php
                            $short_description = mb_substr($product['description'], 0, 100, 'UTF-8');
                            $description_suffix = mb_strlen($product['description'], 'UTF-8') > 100 ? '...' : '';
                        ?>
                        <p class="hero-subtitle"><?= htmlspecialchars($short_description) ?><?= $description_suffix ?></p>
                        <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>" class="btn-hero">شاهد التفاصيل</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-pagination"></div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4"><div class="feature-box"><div class="feature-icon">🎨</div><h3>قطع فريدة</h3><p class="text-muted">كل قطعة هي تحفة فنية لا تتكرر، صُنعت خصيصًا لك.</p></div></div>
                <div class="col-md-4"><div class="feature-box"><div class="feature-icon">❤️</div><h3>صُنع بحب</h3><p class="text-muted">نضع شغفنا في كل التفاصيل لنقدم لك منتجًا يحمل دفء العمل اليدوي.</p></div></div>
                <div class="col-md-4"><div class="feature-box"><div class="feature-icon">🌿</div><h3>مواد عالية الجودة</h3><p class="text-muted">نختار أفضل المواد الطبيعية لضمان جمال واستدامة منتجاتنا.</p></div></div>
            </div>
        </div>
    </section>

    <?php if (!empty($categories)): ?>
    <section class="section bg-light">
        <div class="container">
            <h2 class="section-title text-center">تصفح أقسامنا</h2>
            <div class="row g-4 mt-4">
                <?php foreach ($categories as $category): ?>
                <div class="col-md-4">
                    <a href="products.php?category=<?= urlencode($category['slug']) ?>" class="d-block category-card">
                        <img src="images/categories/<?= htmlspecialchars($category['image']) ?>" alt="<?= htmlspecialchars($category['name']) ?>">
                        <div class="overlay-text"><?= htmlspecialchars($category['name']) ?></div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($latest_products)): ?>
    <section class="section">
        <div class="container">
            <h2 class="section-title text-center">أحدث المنتجات</h2>
            <div class="row g-4">
                <?php foreach ($latest_products as $product): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="product-card">
                        <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>">
                            <img src="images/products/<?= htmlspecialchars($product['display_image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" style="height: 250px; object-fit: cover;">
                        </a>
                        <div class="product-card-body text-center">
                            <h3 class="product-title mt-2">
                                <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>" class="text-decoration-none"><?= htmlspecialchars($product['name']) ?></a>
                            </h3>
                            <p class="product-price mt-2"><?= htmlspecialchars($product['price']) ?> جنيه</p>
                            <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>" class="btn btn-product mt-2">شاهد التفاصيل</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const heroSlider = document.querySelector('.hero-swiper');
    if (!heroSlider || typeof Swiper === 'undefined') return;

    new Swiper(heroSlider, {
        effect: 'fade',
        fadeEffect: { crossFade: true },
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false
        },
        pagination: {
            el: '.hero-swiper .swiper-pagination',
            clickable: true
        },
        navigation: {
            nextEl: '.hero-swiper .swiper-button-next',
            prevEl: '.hero-swiper .swiper-button-prev'
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

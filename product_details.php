<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once 'includes/db_connection.php';

$product_slug = isset($_GET['slug']) ? trim($_GET['slug']) : ''; 

if (empty($product_slug)) { 
    
    header("Location: products.php");
    exit;
}


try {
    
    
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.slug = ?");
    $stmt->execute([$product_slug]); 
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    
    if (!$product) {
        header("Location: products.php");
        exit;
    }

    
    $product_id = $product['id'];

    
    $img_stmt = $pdo->prepare("SELECT image_name FROM product_images WHERE product_id = ? ORDER BY id ASC");
    $img_stmt->execute([$product_id]);
    $product_images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    
    $related_stmt = $pdo->prepare("
        SELECT p.*, (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as main_image
        FROM products p
        WHERE p.category_id = ? AND p.id != ? AND p.stock_quantity > 0
        ORDER BY p.created_at DESC, p.id DESC LIMIT 4
    ");
    $related_stmt->execute([$product['category_id'], $product_id]);
    $related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    
    die("An error occurred while fetching product data.");
}


$main_image = !empty($product['main_image']) ? $product['main_image'] : (!empty($product_images) ? $product_images[0] : 'placeholder.svg');
if (empty($product_images)) {
    $product_images = [$main_image];
}

$page_title = htmlspecialchars_decode($product['name'], ENT_QUOTES) . ' | Noor Handmade';
require_once 'includes/header.php';
?>

<style>
    
    .product-detail-container {
        padding: 20px;
        background-color: #f5f5f5;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    
    .gallery-container {
        position: sticky;
        top: 100px;
    }

    .main-image-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        cursor: crosshair;
    }

    .main-image {
        width: 100%;
        height: auto;
        display: block;
        transition: transform 0.3s ease;
    }

    
    .main-image-wrapper:hover .main-image {
        transform: scale(1.5);
    }

    .thumbnails-strip {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .thumb {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: border-color 0.3s ease;
    }

    .thumb:hover,
    .thumb.active {
        border-color: var(--primary-color);
    }

    
    .product-info h1 {
        font-family: var(--font-headings);
        font-size: 2.8rem;
        color: var(--dark-color);
    }

    .product-category {
        color: #777;
        font-size: 1.1rem;
    }

    .product-price-details {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary-color);
        margin: 20px 0;
    }

    .product-description {
        line-height: 1.8;
        color: #555;
    }

    .quantity-selector {
        display: flex;
        align-items: center;
    }

    .quantity-btn {
        width: 40px;
        height: 40px;
        border: 1px solid #ddd;
        background: #f8f8f8;
        font-size: 1.2rem;
        font-weight: bold;
    }

    #quantity-input {
        width: 60px;
        height: 40px;
        text-align: center;
        border: 1px solid #ddd;
        border-left: none;
        border-right: none;
    }

    
    #quantity-input::-webkit-outer-spin-button,
    #quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    #quantity-input[type=number] {
        -moz-appearance: textfield;
    }

    .btn-add-to-cart-lg {
        padding: 0.8rem 2rem;
        font-size: 1.2rem;
        font-weight: 700;
        border-radius: 50px;
    }

    
    .related-products-section {
        background-color: var(--light-color);
        padding: 80px 0;
    }

    .product-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .product-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .product-card-body {
        padding: 20px;
        text-align: center;
    }

    .product-title {
        font-size: 1.4rem;
        color: var(--dark-color);
    }

    .product-price {
        color: var(--primary-color);
        font-size: 1.2rem;
        font-weight: 700;
    }

    .swiper {
        width: 100%;
        height: 100%;
        border-radius: 15px;
    }

    .swiper-slide {
        text-align: center;
        font-size: 18px;
        background: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .swiper-slide img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .mySwiper2 {
        height: 450px;
        
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .mySwiper2 .swiper-slide > a {
        display: block;
        width: 100%;
        height: 100%;
    }

    .mySwiper {
        height: 100px;
        
        box-sizing: border-box;
        padding: 10px 0;
    }

    .mySwiper .swiper-slide {
        width: 25%;
        height: 100%;
        opacity: 0.5;
        cursor: pointer;
        transition: opacity 0.3s ease;
        border-radius: 10px;
    }

    .mySwiper .swiper-slide:hover {
        opacity: 1;
    }

    .mySwiper .swiper-slide-thumb-active {
        opacity: 1;
        border: 2px solid var(--primary-color, #0d6efd);
    }

    
    .swiper-button-next,
    .swiper-button-prev {
        color: var(--primary-color, #0d6efd) !important;
        background-color: rgba(255, 255, 255, 0.7);
        width: 44px;
        height: 44px;
        border-radius: 50%;
        transition: background-color 0.3s ease;
    }

    .swiper-button-next:hover,
    .swiper-button-prev:hover {
        background-color: white;
    }

    .swiper-button-next:after,
    .swiper-button-prev:after {
        font-size: 20px !important;
        font-weight: 900;
    }

    
    .swiper-pagination-bullet {
        background: var(--primary-color, #0d6efd) !important;
    }

    @media (max-width: 767.98px) {
        .product-detail-container {
            width: calc(100% - 24px);
            padding: 12px;
            overflow: hidden;
        }

        .product-detail-container > .row {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1.75rem;
        }

        .mySwiper2 {
            height: auto;
            aspect-ratio: 4 / 3;
        }

        .mySwiper {
            height: 84px;
        }

        .product-info h1,
        .product-price-details {
            font-size: 2rem;
        }

        .product-info > .d-flex.align-items-center {
            flex-wrap: wrap;
            gap: 12px;
        }

        .quantity-selector {
            margin-inline-end: 0 !important;
        }

        .btn-add-to-cart-lg {
            min-width: 180px;
            min-height: 48px;
        }
    }
</style>
<div class="container product-detail-container">
    <div class="row g-5">
        <div class="col-lg-6">
            <div class="swiper mySwiper2">
                <div class="swiper-wrapper">
                    <?php foreach ($product_images as $image): ?>
                        <div class="swiper-slide">
                            <a href="images/products/<?= htmlspecialchars($image) ?>" data-fancybox="gallery">
                                <img src="images/products/<?= htmlspecialchars($image) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>" />
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>

            <div thumbsSlider="" class="swiper mySwiper mt-2">
                <div class="swiper-wrapper">
                    <?php foreach ($product_images as $image): ?>
                        <div class="swiper-slide">
                            <img src="images/products/<?= htmlspecialchars($image) ?>" />
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="product-info">
                <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <div class="product-price-details"><?= number_format($product['price'], 2) ?> EGP</div>
                <?php if ((int) $product['stock_quantity'] < 1): ?>
                    <div class="alert alert-danger">هذا المنتج غير متوفر حاليًا.</div>
                <?php elseif ((int) $product['stock_quantity'] <= 5): ?>
                    <div class="alert alert-warning">متبقي <?= (int) $product['stock_quantity'] ?> فقط في المخزون.</div>
                <?php endif; ?>
                <div class="product-description">
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>

                <hr class="my-4">

                <div class="d-flex align-items-center">
                    <div class="quantity-selector me-3">
                        <button class="btn quantity-btn" id="decrease-qty">-</button>
                        <input type="number" id="quantity-input" value="1" min="1" max="<?= min((int) $product['stock_quantity'], 99) ?>">
                        <button class="btn quantity-btn" id="increase-qty">+</button>
                    </div>
                    <button class="btn btn-primary-custom btn-add-to-cart-lg flex-grow-1"
                        data-product-id="<?= $product['id'] ?>" <?= (int) $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>
                        <i class="fas fa-cart-plus me-2"></i> أضف إلى السلة
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($related_products)): ?>
    <div class="related-products-section">
        <div class="container">
            <h2 class="section-title text-center">منتجات مشابهة</h2>
            <div class="row g-4">
                <?php foreach ($related_products as $related_product): ?>
                    <div class="col-md-6 col-lg-3">
                        <a href="product_details.php?slug=<?= $related_product['slug'] ?>" class="product-card d-block">
                            <img src="images/products/<?= htmlspecialchars($related_product['main_image'] ?? 'placeholder.svg') ?>"
                                class="card-img-top" alt="<?= htmlspecialchars($related_product['name']) ?>">
                            <div class="product-card-body">
                                <h3 class="product-title"><?= htmlspecialchars($related_product['name']) ?></h3>
                                <p class="product-price mt-2"><?= htmlspecialchars($related_product['price']) ?> EGP</p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<script>
    
    const qtyInput = document.getElementById('quantity-input');
    document.getElementById('increase-qty').addEventListener('click', () => {
        const maxQty = parseInt(qtyInput.max);
        if (parseInt(qtyInput.value) < maxQty) {
            qtyInput.value = parseInt(qtyInput.value) + 1;
        }
    });
    document.getElementById('decrease-qty').addEventListener('click', () => {
        const currentQty = parseInt(qtyInput.value);
        if (currentQty > 1) {
            qtyInput.value = currentQty - 1;
        }
    });

    

    
    var swiper = new Swiper(".mySwiper", {
        loop: false,
        spaceBetween: 10,
        slidesPerView: 4, 
        freeMode: true,
        watchSlidesProgress: true,
    });

    
    var swiper2 = new Swiper(".mySwiper2", {
        loop: true,
        effect: 'fade', 
        fadeEffect: {
            crossFade: true
        },
        spaceBetween: 10,
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true,
        },
        thumbs: {
            swiper: swiper,
        },
        keyboard: {
            enabled: true, 
        },
    });

    
    Fancybox.bind('[data-fancybox="gallery"]', {
        
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButton = document.querySelector('.btn-add-to-cart-lg');

    // تحقق من وجود الزر قبل إضافة المستمع
    if (addToCartButton) {
        addToCartButton.addEventListener('click', function() {
            // isUserLoggedIn هو متغير عام قادم من footer.php
            const isUserLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

            if (!isUserLoggedIn) {
                Swal.fire({
                    title: 'يرجى تسجيل الدخول',
                    text: "يجب عليك تسجيل الدخول أولاً لتتمكن من إضافة المنتجات إلى السلة.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--primary-color)',
                    cancelButtonColor: '#aaa',
                    confirmButtonText: 'تسجيل الدخول',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'login.php';
                    }
                });
                return; // إيقاف التنفيذ
            }

            // إذا كان المستخدم مسجلاً دخوله
            const productId = this.dataset.productId;
            const quantityInput = document.getElementById('quantity-input');
            const quantity = parseInt(quantityInput.value);
            const maxQuantity = <?= min((int) $product['stock_quantity'], 99) ?>;
            const Toast = Swal.mixin({ toast: true, position: 'top-start', showConfirmButton: false, timer: 2500 });

            if (!Number.isInteger(quantity) || quantity <= 0 || quantity > maxQuantity) {
                Toast.fire({ icon: 'error', title: 'الرجاء تحديد كمية صالحة.' });
                return;
            }

            addToCartButton.disabled = true;
            fetch('cart_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ action: 'add', product_id: productId, quantity: quantity })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('cart-counter').textContent = data.cart_total_items;
                    Toast.fire({ icon: 'success', title: data.message });
                } else {
                    Toast.fire({ icon: 'error', title: data.message || 'حدث خطأ ما.' });
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                Toast.fire({ icon: 'error', title: 'حدث خطأ في الشبكة.' });
            })
            .finally(() => {
                addToCartButton.disabled = <?= (int) $product['stock_quantity'] < 1 ? 'true' : 'false' ?>;
            });
        });
    }
});
</script>

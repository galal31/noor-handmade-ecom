<?php

require_once __DIR__ . '/security.php';
start_secure_session();
send_security_headers();

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/cart_functions.php';
require_once __DIR__ . '/app_config.php';

if (!isset($page_title)) {
    $page_title = 'Noor Handmade | نور للمنتجات اليدوية';
}

$seo_noindex_pages = [
    'cart.php',
    'checkout.php',
    'forgot_password.php',
    'login.php',
    'my_orders.php',
    'register.php',
    'resend_verification.php',
    'reset_password.php',
    'thank_you.php',
    'track_order.php',
    'verify.php',
];
$seo_current_page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$page_description = trim((string) ($page_description ?? 'اكتشف منتجات Noor Handmade اليدوية المصنوعة بعناية، واختر قطعًا مميزة تضيف لمسة خاصة إلى يومك.'));
$page_description = preg_replace('/\s+/u', ' ', strip_tags($page_description)) ?: '';
if (mb_strlen($page_description, 'UTF-8') > 165) {
    $page_description = rtrim(mb_substr($page_description, 0, 162, 'UTF-8')) . '...';
}

if (!isset($page_robots)) {
    $page_robots = in_array($seo_current_page, $seo_noindex_pages, true)
        ? 'noindex, nofollow'
        : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
}

$seo_base_url = rtrim(app_base_url(), '/');
if (!isset($canonical_url)) {
    $canonical_path = isset($page_canonical_path)
        ? ltrim((string) $page_canonical_path, '/')
        : $seo_current_page;
    $canonical_url = $seo_base_url . '/' . $canonical_path;
}
$canonical_url = (string) $canonical_url;
$page_og_type = (string) ($page_og_type ?? 'website');
$page_image = trim((string) ($page_image ?? 'images/logo.jpeg'));
if ($page_image !== '' && !preg_match('~^https?://~i', $page_image)) {
    $page_image = $seo_base_url . '/' . ltrim($page_image, '/');
}
$page_image_alt = trim((string) ($page_image_alt ?? $page_title));

if (stripos($page_robots, 'noindex') !== false && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow', true);
}


$cart_total_items = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $header_cart_data = normalize_cart($pdo, $_SESSION['cart']);
    $cart_total_items = $header_cart_data['cart_total_items'];
}
$cart_csrf_token = get_or_create_csrf_token('cart_csrf_token');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="images/logo.jpeg">
    <meta name="csrf-token" content="<?= htmlspecialchars($cart_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:locale" content="ar_EG">
    <meta property="og:site_name" content="Noor Handmade">
    <meta property="og:type" content="<?= htmlspecialchars($page_og_type, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($page_image !== ''): ?>
        <meta property="og:image" content="<?= htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8') ?>">
        <meta property="og:image:alt" content="<?= htmlspecialchars($page_image_alt, ENT_QUOTES, 'UTF-8') ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?= htmlspecialchars($page_image, ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.1/nouislider.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=El+Messiri:wght@600;700&display=swap"
        rel="stylesheet">
    <?php foreach (($page_stylesheets ?? []) as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

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
            background-color: #fff;
            padding-top: 80px;
            /* Prevent content from hiding behind fixed navbar */
        }

        .navbar {
            background-color: #fff !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 0.8rem 0;
            font-family: var(--font-body);
        }

        .navbar-brand {
            font-family: var(--font-headings);
            color: var(--primary-color) !important;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .nav-link {
            color: var(--dark-color) !important;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0 0.8rem;
            transition: color 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
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

        a.btn-cart:hover,
        a.btn-cart:focus-visible {
            background-color: #cf6812 !important;
            border-color: #cf6812 !important;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(230, 126, 34, 0.4);
            color: #fff !important;
        }

        .dropdown-menu {
            font-family: var(--font-body);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .btn-outline-primary-custom {
            color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 50px;
            font-weight: 700;
            padding: 0.6rem 1rem;
        }

        .btn-outline-primary-custom:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
            border-radius: 50px;
            font-weight: 700;
            padding: 0.6rem 1rem;
        }

        .btn-primary-custom:hover {
            background-color: #117a65;
            border-color: #117a65;
            color: #fff;
            box-shadow: 0 8px 18px rgba(17, 122, 101, 0.24);
        }
    </style>
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg fixed-top">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <img src="images/logo.jpeg" alt="Noor Handmade Logo" height="45"
                        class="d-inline-block align-middle me-2" style="border-radius: 8px;">
                    Noor Handmade
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span
                        class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link" href="index.php">الرئيسية</a></li>
                        <li class="nav-item"><a class="nav-link" href="products.php">المنتجات</a></li>
                        <li class="nav-item"><a class="nav-link" href="track_order.php">تتبع طلبك</a></li>
                    </ul>
                    <div class="d-flex align-items-center">
                        <a href="cart.php" class="btn btn-cart me-3">
                            <i class="fas fa-shopping-cart me-2"></i> السلة
                            <span class="badge bg-dark ms-1" id="cart-counter"><?= $cart_total_items ?></span>
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary-custom dropdown-toggle" type="button" id="userMenu"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-2"></i> مرحباً,
                                    <?= htmlspecialchars(explode(' ', $_SESSION['user_full_name'])[0]) ?>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="userMenu">
                                    <li><a class="dropdown-item" href="profile.php">ملفي الشخصي</a></li>
                                    <li><a class="dropdown-item" href="my_orders.php">طلباتي</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="logout.php">تسجيل الخروج</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="d-flex">
                                <a href="login.php" class="btn btn-outline-primary-custom me-2"><i
                                        class="fas fa-sign-in-alt me-1"></i> دخول</a>
                                <a href="register.php" class="btn btn-primary-custom"><i class="fas fa-user-plus me-1"></i>
                                    حساب جديد</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

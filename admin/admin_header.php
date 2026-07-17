<?php
require_once __DIR__ . '/admin_bootstrap.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="css/admin_style.css?v=2">
</head>
<body>
<div class="page-wrapper">
    <nav id="sidebar" class="sidebar-wrapper">
        <div class="sidebar-content">
            <div class="sidebar-brand">
                <a href="index.php">Noor Handmade</a>
            </div>
            <div class="sidebar-header">
                <div class="user-info">
                    <span class="user-name">مرحباً, <strong><?= htmlspecialchars($_SESSION['admin_full_name']) ?></strong></span>
                </div>
            </div>
<div class="sidebar-menu">
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <ul>
        <li class="header-menu"><span>عام</span></li>
        <li><a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>"><i class="fa fa-tachometer-alt"></i><span>الرئيسية</span></a></li>
        <li><a href="manage_categories.php" class="<?= ($currentPage == 'manage_categories.php') ? 'active' : '' ?>"><i class="fa fa-folder-open"></i><span>إدارة الأقسام</span></a></li>
        <li><a href="manage_products.php" class="<?= ($currentPage == 'manage_products.php') ? 'active' : '' ?>"><i class="fa fa-shopping-bag"></i><span>إدارة المنتجات</span></a></li>
        <li><a href="manage_orders.php" class="<?= ($currentPage == 'manage_orders.php') ? 'active' : '' ?>"><i class="fa fa-chart-line"></i><span>عرض الطلبات</span></a></li>
        
        <li><a href="manage_users.php" class="<?= ($currentPage == 'manage_users.php') ? 'active' : '' ?>"><i class="fa fa-users"></i><span>إدارة المستخدمين</span></a></li>
        </ul>
</div>
        </div>
    </nav>
    <button type="button" class="sidebar-backdrop" aria-label="إغلاق القائمة الجانبية"></button>

    <main class="page-content">
        <header class="header fixed-top">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" class="navbar-brand sidebar-toggle" id="toggle-sidebar"
                            aria-label="فتح القائمة الجانبية" aria-controls="sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link" href="logout.php"><i class="fa fa-power-off"></i> تسجيل الخروج</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        <div class="container-fluid content-container">

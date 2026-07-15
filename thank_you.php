<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';

$tracking_code = trim((string) ($_GET['track'] ?? ''));
$order = null;
if ($tracking_code !== '') {
    $stmt = $pdo->prepare('SELECT id, tracking_code, status FROM orders WHERE tracking_code = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$tracking_code, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    http_response_code(404);
}

$page_title = $order ? 'تم استلام طلبك | Noor Handmade' : 'الطلب غير موجود | Noor Handmade';
require_once 'includes/header.php';
?>

<style>
    .thank-you-card {
        padding: 3rem;
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        text-align: center;
    }
    .icon-container {
        width: 100px;
        height: 100px;
        margin: 0 auto 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 50px;
        color: #fff;
        background-color: var(--primary-color);
    }
    .tracking-code {
        font-size: 1.5rem;
        font-weight: bold;
        background-color: var(--light-color);
        padding: 10px 20px;
        border-radius: 10px;
        display: inline-block;
        letter-spacing: 2px;
    }
</style>

<div class="container d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 200px);">
    <div class="col-md-8">
        <div class="card thank-you-card">
            <?php if ($order): ?>
                <div class="icon-container"><i class="fas fa-check"></i></div>
                <h1 style="font-family: var(--font-headings);">شكرًا لك! تم استلام طلبك.</h1>
                <p class="lead text-muted">طلبك قيد المراجعة وسنتواصل معك قريبًا لتأكيد التفاصيل وموعد التوصيل.</p>
                <div class="mt-4">
                    <p>استخدم كود التتبع التالي لمتابعة الطلب:</p>
                    <div class="tracking-code my-3"><?= htmlspecialchars($order['tracking_code']) ?></div>
                    <div>
                        <a href="track_order.php?tracking_code=<?= urlencode($order['tracking_code']) ?>">متابعة حالة الطلب الآن</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="icon-container bg-danger"><i class="fas fa-times"></i></div>
                <h1 style="font-family: var(--font-headings);">الطلب غير موجود</h1>
                <p class="lead text-muted">تعذر التحقق من هذا الطلب أو أنه لا يخص حسابك.</p>
                <a href="my_orders.php" class="btn btn-primary-custom mt-3">عرض طلباتي</a>
            <?php endif; ?>

            <a href="products.php" class="btn btn-outline-primary-custom mt-4">العودة للتسوق</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

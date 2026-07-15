<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once 'includes/db_connection.php';
require_once 'includes/order_functions.php';

$tracking_code = strtoupper(trim((string) ($_GET['tracking_code'] ?? '')));
$order_status = null;
$status_detail = null;
$error_message = '';
$is_rate_limited = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $tracking_code !== '') {
    $client_key = tracking_client_key();
    $blocked_seconds = tracking_block_seconds($pdo, $client_key);

    if ($blocked_seconds > 0) {
        http_response_code(429);
        $is_rate_limited = true;
        $error_message = 'تم إيقاف محاولات التتبع مؤقتًا. حاول مرة أخرى بعد ' . max(1, (int) ceil($blocked_seconds / 60)) . ' دقيقة.';
    } else {
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE tracking_code = ? LIMIT 1');
        $stmt->execute([$tracking_code]);
        $order_status = $stmt->fetchColumn() ?: null;

        if ($order_status !== null) {
            clear_tracking_attempts($pdo, $client_key);
            $status_detail = order_status_details()[$order_status] ?? null;
            if (!$status_detail) {
                $error_message = 'حالة الطلب غير متاحة حاليًا. يرجى التواصل معنا.';
            }
        } else {
            register_invalid_tracking_attempt($pdo, $client_key);
            $error_message = 'عفوًا، كود التتبع غير صحيح. تأكد منه وحاول مرة أخرى.';
        }
    }
}

$page_title = 'تتبع طلبك | Noor Handmade';
require_once 'includes/header.php';
?>

<style>
    .tracking-page-container {
        padding: 50px 0;
        background-color: var(--light-color);
        min-height: calc(100vh - 200px);
    }
    .track-form-card, .track-result-card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.08);
        padding: 2.5rem;
        background: #fff;
    }
    .tracking-wrapper {
        padding: 40px 10px;
        position: relative;
        overflow-x: hidden;
    }
    .tracking-road {
        height: 10px;
        background: #ddd;
        border-radius: 5px;
        position: relative;
        transform: rotateX(45deg) translateY(50px) scale(0.9);
    }
    .tracking-progress {
        height: 100%;
        background: var(--primary-color);
        border-radius: 5px;
        transition: width 1s ease-in-out;
    }
    .tracking-truck {
        position: absolute;
        bottom: 5px;
        font-size: 50px;
        color: var(--secondary-color);
        transform: scaleX(-1);
        transition: right 1s ease-in-out;
    }
    .tracking-stops {
        display: flex;
        justify-content: space-between;
        margin-top: 80px;
    }
    .stop {
        text-align: center;
        width: 25%;
        color: #aaa;
    }
    .stop.active {
        color: var(--primary-color);
        font-weight: bold;
    }
</style>

<div class="container tracking-page-container">
    <div class="col-lg-8 mx-auto">
        <div class="track-form-card mb-5">
            <h1 class="text-center mb-4" style="font-family: var(--font-headings);">تتبع طلبك</h1>
            <p class="text-center text-muted">أدخل كود التتبع الذي استلمته بعد تأكيد طلبك.</p>
            <form action="track_order.php" method="GET" class="mt-4">
                <div class="input-group input-group-lg">
                    <input type="text" class="form-control" name="tracking_code" placeholder="مثال: NOOR-A1B2C3D4" value="<?= htmlspecialchars($tracking_code) ?>" maxlength="15" required <?= $is_rate_limited ? 'disabled' : '' ?>>
                    <button class="btn btn-primary-custom" type="submit" <?= $is_rate_limited ? 'disabled' : '' ?>>
                        <i class="fas fa-search me-2"></i> تتبع
                    </button>
                </div>
            </form>
        </div>

        <?php if ($tracking_code !== ''): ?>
            <?php if ($error_message): ?>
                <div class="alert <?= $is_rate_limited ? 'alert-warning' : 'alert-danger' ?> text-center">
                    <h5 class="mb-0"><?= htmlspecialchars($error_message) ?></h5>
                </div>
            <?php elseif ($order_status && $status_detail): ?>
                <div class="track-result-card">
                    <h3 class="text-center mb-3">حالة الطلب: <span class="text-primary"><?= htmlspecialchars($order_status) ?></span></h3>
                    <p class="text-center text-muted fs-5">
                        <i class="<?= htmlspecialchars($status_detail['icon']) ?> me-2"></i>
                        <?= htmlspecialchars($status_detail['text']) ?>
                    </p>

                    <?php if ($status_detail['step'] === 0): ?>
                        <div class="alert alert-danger text-center mt-4 mb-0">
                            <i class="fas fa-ban fa-2x mb-2"></i>
                            <div>هذا الطلب ملغي ولا توجد له مراحل شحن نشطة.</div>
                        </div>
                    <?php else: ?>
                        <?php
                            $current_step = (int) $status_detail['step'];
                            $progress_percentage = $current_step === 4 ? 100 : ($current_step - 1) * 33.33;
                        ?>
                        <div class="tracking-wrapper">
                            <div class="tracking-road">
                                <div class="tracking-progress" style="width: <?= $progress_percentage ?>%;"></div>
                                <div class="tracking-truck" style="right: calc(<?= $progress_percentage ?>% - 25px);"><i class="fas fa-truck-moving"></i></div>
                            </div>
                            <div class="tracking-stops">
                                <?php foreach (['قيد المراجعة', 'تم التأكيد', 'جاري الشحن', 'تم التسليم'] as $index => $label): ?>
                                    <div class="stop <?= ($index + 1) <= $current_step ? 'active' : '' ?>">
                                        <div class="icon"><i class="<?= htmlspecialchars(order_status_details()[$label]['icon']) ?>"></i></div>
                                        <div><?= htmlspecialchars($label) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

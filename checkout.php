<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/cart_functions.php';
require_once 'includes/order_functions.php';

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$errors = [];
$cart_data = normalize_cart($pdo, $_SESSION['cart']);
$checkout_items = array_values($cart_data['items']);
$grand_total = $cart_data['grand_total_value'];

if (empty($checkout_items)) {
    $_SESSION['cart_flash'] = 'لا توجد منتجات متاحة لإتمام الطلب.';
    header('Location: cart.php');
    exit;
}

$checkout_csrf_token = get_or_create_csrf_token('checkout_csrf_token');
if (empty($_SESSION['checkout_token']) || !is_string($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}
$checkout_token = $_SESSION['checkout_token'];

$full_name = (string) ($_SESSION['user_full_name'] ?? '');
$phone = '';
$address = '';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $email = (string) ($_SESSION['user_email'] ?? '');
    $user_id = (int) $_SESSION['user_id'];

    if (!is_valid_csrf_token('checkout_csrf_token', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.';
    }
    if (!isset($_POST['checkout_token']) || !hash_equals($checkout_token, (string) $_POST['checkout_token'])) {
        $errors[] = 'تعذر التحقق من عملية الطلب. حدّث الصفحة وحاول مرة أخرى.';
    }
    if ($full_name === '' || $phone === '' || $address === '') {
        $errors[] = 'الرجاء ملء جميع الحقول المطلوبة (الاسم، الهاتف، العنوان).';
    }
    if (mb_strlen($full_name) > 255) {
        $errors[] = 'الاسم أطول من الحد المسموح.';
    }
    if (!preg_match('/^[0-9+()\-\s]{7,20}$/u', $phone)) {
        $errors[] = 'رقم الهاتف غير صالح.';
    }
    if (mb_strlen($address) > 2000 || mb_strlen($notes) > 1000) {
        $errors[] = 'العنوان أو الملاحظات أطول من الحد المسموح.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $locked_products = get_cart_product_map($pdo, array_keys($_SESSION['cart']), true);
            $locked_items = [];
            $locked_grand_total = 0.0;

            foreach ($_SESSION['cart'] as $product_id => $item) {
                $product_id = (int) $product_id;
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                $product = $locked_products[$product_id] ?? null;

                if (!$product) {
                    throw new RuntimeException('أحد المنتجات لم يعد متاحًا. ارجع إلى السلة لمراجعتها.');
                }
                if ($quantity < 1 || $quantity > CART_MAX_QUANTITY_PER_PRODUCT) {
                    throw new RuntimeException('إحدى كميات المنتجات غير صالحة. ارجع إلى السلة لمراجعتها.');
                }
                if ($quantity > (int) $product['stock_quantity']) {
                    throw new RuntimeException('الكمية المطلوبة من "' . $product['name'] . '" لم تعد متاحة.');
                }

                $locked_items[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => (float) $product['price'],
                ];
                $locked_grand_total += round((float) $product['price'] * $quantity, 2);
            }

            $locked_grand_total = round($locked_grand_total, 2);
            if (empty($locked_items) || $locked_grand_total <= 0) {
                throw new RuntimeException('لا يمكن إنشاء طلب فارغ. ارجع إلى السلة وأضف منتجًا متاحًا.');
            }

            $tracking_code = 'NOOR-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $pdo->prepare(
                'INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, customer_address, subtotal_price, shipping_cost, total_price, tracking_code, checkout_token, notes)
                 VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?)'
            );
            $stmt->execute([$user_id, $full_name, $email, $phone, $address, $locked_grand_total, $locked_grand_total, $tracking_code, $checkout_token, $notes]);
            $order_id = $pdo->lastInsertId();

            $history_stmt = $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, note) VALUES (?, NULL, ?, ?)');
            $history_stmt->execute([$order_id, 'قيد المراجعة', 'تم إنشاء الطلب بواسطة العميل']);

            $insert_item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)');
            $decrement_stock = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');

            foreach ($locked_items as $locked_item) {
                $product = $locked_item['product'];
                $quantity = $locked_item['quantity'];
                $insert_item->execute([$order_id, $product['id'], $product['name'], $quantity, $locked_item['price']]);
                $decrement_stock->execute([$quantity, $product['id'], $quantity]);
                if ($decrement_stock->rowCount() !== 1) {
                    throw new RuntimeException('تغير المخزون أثناء تنفيذ الطلب. حاول مرة أخرى.');
                }
            }

            $pdo->commit();
            $_SESSION['last_order_tracking_code'] = $tracking_code;
            unset($_SESSION['cart'], $_SESSION['checkout_token'], $_SESSION['checkout_csrf_token']);
            header('Location: thank_you.php?track=' . urlencode($tracking_code));
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $existing_order = $pdo->prepare('SELECT tracking_code FROM orders WHERE checkout_token = ? LIMIT 1');
                $existing_order->execute([$checkout_token]);
                $existing_tracking_code = $existing_order->fetchColumn();
                if ($existing_tracking_code) {
                    $_SESSION['last_order_tracking_code'] = $existing_tracking_code;
                    unset($_SESSION['cart'], $_SESSION['checkout_token'], $_SESSION['checkout_csrf_token']);
                    header('Location: thank_you.php?track=' . urlencode($existing_tracking_code));
                    exit;
                }
            }

            error_log('Checkout failed: ' . $e->getMessage());
            $errors[] = 'تعذر إنشاء الطلب حاليًا. يرجى المحاولة مرة أخرى.';
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .checkout-form .form-control {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #ddd;
    }
    .order-summary {
        background-color: var(--light-color);
        border-radius: 15px;
        padding: 2rem;
        position: sticky;
        top: 100px;
    }
</style>

<div class="container py-5">
    <h1 class="mb-4" style="font-family: var(--font-headings);">إتمام الطلب</h1>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h4 class="mb-4">معلومات التوصيل</h4>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach (array_unique($errors) as $error): ?>
                                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form class="checkout-form" method="POST" action="checkout.php" id="checkout-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($checkout_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="checkout_token" value="<?= htmlspecialchars($checkout_token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="fullName" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fullName" name="full_name" maxlength="255" value="<?= htmlspecialchars($full_name) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" disabled>
                            <small class="text-muted">يتم جلبه تلقائيًا من حسابك.</small>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">رقم هاتف واتساب <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" maxlength="20" value="<?= htmlspecialchars($phone) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">العنوان التفصيلي <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" maxlength="2000" required><?= htmlspecialchars($address) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات إضافية (اختياري)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000"><?= htmlspecialchars($notes) ?></textarea>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary-custom btn-lg" id="checkout-submit">تأكيد الطلب (الدفع عند الاستلام)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="order-summary">
                <h4 class="mb-4">ملخص طلبك</h4>
                <?php foreach ($checkout_items as $item): ?>
                    <div class="d-flex justify-content-between small mb-2 gap-3">
                        <span><?= htmlspecialchars($item['product']['name']) ?> × <?= (int) $item['quantity'] ?></span>
                        <strong class="text-nowrap"><?= number_format($item['subtotal'], 2) ?> جنيه</strong>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span>الإجمالي</span>
                    <strong><?= number_format($grand_total, 2) ?> جنيه</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>رسوم الشحن</span>
                    <strong>سيتم تحديدها</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between fs-4">
                    <strong>المجموع الكلي</strong>
                    <strong><?= number_format($grand_total, 2) ?> جنيه</strong>
                </div>
                <div class="alert alert-info mt-4 mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    الدفع سيكون نقدًا عند استلام الطلب.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('checkout-form').addEventListener('submit', function () {
    const submitButton = document.getElementById('checkout-submit');
    submitButton.disabled = true;
    submitButton.textContent = 'جاري تأكيد الطلب...';
});
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
// cart.php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once 'includes/db_connection.php';
require_once 'includes/cart_functions.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_data = normalize_cart($pdo, $_SESSION['cart']);
$cart_items = array_values($cart_data['items']);
$grand_total = $cart_data['grand_total_value'];
$cart_messages = $cart_data['changes'];
if (!empty($_SESSION['cart_flash'])) {
    $cart_messages[] = (string) $_SESSION['cart_flash'];
    unset($_SESSION['cart_flash']);
}

require_once 'includes/header.php';
?>

<style>
    .cart-page {
        min-height: 60vh;
        background-color: #f8f9fa;
        padding: 50px 0;
    }
    
    .cart-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        background: #fff;
        overflow: hidden;
    }

    .cart-header {
        background-color: #fff;
        border-bottom: 2px solid #f0f0f0;
        padding: 20px;
        font-family: var(--font-headings);
        font-weight: bold;
    }

    .cart-item-row {
        border-bottom: 1px solid #eee;
        transition: background 0.3s;
    }
    
    .cart-item-row:hover {
        background-color: #fafafa;
    }

    .cart-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
    }

    .qty-input-group {
        width: 120px;
        margin: 0 auto;
    }
    
    .qty-btn {
        background: #f1f1f1;
        border: 1px solid #ddd;
        color: #333;
        font-weight: bold;
    }
    
    .qty-input {
        text-align: center;
        border-top: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        border-left: none;
        border-right: none;
        background: #fff;
    }

    .summary-card {
        position: sticky;
        top: 100px;
    }

    .btn-checkout {
        background-color: var(--primary-color);
        color: #fff;
        padding: 12px;
        font-weight: bold;
        border-radius: 50px;
        transition: 0.3s;
    }

    .btn-checkout:hover {
        background-color: var(--dark-color);
        color: #fff;
        transform: translateY(-2px);
    }

    /* Mobile Responsive Tweaks */
    @media (max-width: 768px) {
        .cart-table thead {
            display: none; /* إخفاء الهيدر في الموبايل */
        }
        .cart-item-row td {
            display: block;
            text-align: center;
            border: none;
        }
        .cart-item-row {
            padding: 15px;
            border-bottom: 5px solid #f8f9fa;
        }
        .qty-input-group {
            margin: 10px auto;
        }
    }
</style>

<div class="cart-page">
    <div class="container">
        <h1 class="mb-4 text-center" style="font-family: var(--font-headings);">سلة المشتريات</h1>

        <?php if (!empty($cart_messages)): ?>
            <div class="alert alert-warning">
                <?php foreach (array_unique($cart_messages) as $message): ?>
                    <div><?= htmlspecialchars($message) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-shopping-basket fa-5x text-muted" style="opacity: 0.3;"></i>
                </div>
                <h3 class="text-muted mb-3">سلة المشتريات فارغة!</h3>
                <p class="text-muted mb-4">لم تقم بإضافة أي منتجات للسلة بعد.</p>
                <a href="products.php" class="btn btn-primary-custom btn-lg px-5">تصفح المنتجات</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="cart-card">
                        <div class="cart-header d-none d-md-block">
                            <div class="row">
                                <div class="col-md-5">المنتج</div>
                                <div class="col-md-2 text-center">السعر</div>
                                <div class="col-md-3 text-center">الكمية</div>
                                <div class="col-md-2 text-center">الإجمالي</div>
                            </div>
                        </div>
                        
                        <div class="cart-body">
                            <?php foreach ($cart_items as $item): 
                                $product = $item['product'];
                            ?>
                            <div class="cart-item-row p-3" id="row-<?= $product['id'] ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-5 d-flex align-items-center mb-3 mb-md-0">
                                        <button class="btn btn-link text-danger p-0 me-3" onclick="removeItem(<?= $product['id'] ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        
                                        <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>">
                                            <img src="images/products/<?= htmlspecialchars($product['main_image'] ?? 'placeholder.svg') ?>" 
                                                 class="cart-img" alt="<?= htmlspecialchars($product['name']) ?>">
                                        </a>
                                        
                                        <div class="ms-3">
                                            <h6 class="mb-1 fw-bold">
                                                <a href="product_details.php?slug=<?= htmlspecialchars($product['slug']) ?>" class="text-decoration-none text-dark">
                                                    <?= htmlspecialchars($product['name']) ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted d-md-none">سعر الوحدة: <?= number_format($product['price'], 2) ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-2 text-center d-none d-md-block">
                                        <span class="fw-bold"><?= number_format($product['price'], 2) ?></span>
                                    </div>

                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <div class="input-group qty-input-group">
                                            <button class="btn qty-btn" type="button" onclick="updateQty(<?= $product['id'] ?>, -1)">-</button>
                                            <input type="text" class="form-control qty-input" 
                                                   id="qty-<?= $product['id'] ?>" 
                                                   value="<?= $item['quantity'] ?>" readonly>
                                            <button class="btn qty-btn" type="button" onclick="updateQty(<?= $product['id'] ?>, 1)">+</button>
                                        </div>
                                    </div>

                                    <div class="col-md-2 text-center">
                                        <span class="fw-bold text-primary" id="subtotal-<?= $product['id'] ?>">
                                            <?= number_format($item['subtotal'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="cart-card summary-card p-4">
                        <h4 class="mb-4" style="font-family: var(--font-headings);">ملخص الطلب</h4>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">عدد المنتجات</span>
                            <span class="fw-bold" id="total-items-count"><?= array_sum(array_column($cart_items, 'quantity')) ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">المجموع الفرعي</span>
                            <span class="fw-bold" id="cart-grand-total-display"><?= number_format($grand_total, 2) ?></span>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fs-5 fw-bold">الإجمالي الكلي</span>
                            <span class="fs-4 fw-bold text-primary">
                                <span id="final-total-display"><?= number_format($grand_total, 2) ?></span> جنيه
                            </span>
                        </div>

                        <a href="checkout.php" class="btn btn-checkout w-100 mb-3">
                            إتمام الطلب <i class="fas fa-check-circle ms-2"></i>
                        </a>
                        
                        <a href="products.php" class="btn btn-outline-secondary w-100 rounded-pill">
                            متابعة التسوق
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // دالة لتحديث الكمية (زيادة أو نقصان)
    function updateQty(productId, change) {
        const input = document.getElementById(`qty-${productId}`);
        const row = document.getElementById(`row-${productId}`);
        if (!input || !row || row.dataset.updating === '1') return;
        let currentQty = parseInt(input.value);
        let newQty = currentQty + change;

        if (newQty < 1) return; // لا نسمح بأقل من 1

        sendCartRequest('update_quantity', productId, newQty);
    }

    // دالة لحذف المنتج
    function removeItem(productId) {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: "سيتم حذف هذا المنتج من السلة.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'نعم، احذفه!',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                sendCartRequest('remove', productId);
            }
        });
    }

    // الدالة الرئيسية للتواصل مع السيرفر (cart_handler.php)
    function sendCartRequest(action, productId, quantity = null) {
        const row = document.getElementById(`row-${productId}`);
        if (row) {
            row.dataset.updating = '1';
            row.querySelectorAll('button').forEach(button => button.disabled = true);
        }
        const data = {
            action: action,
            product_id: productId
        };
        if (quantity !== null) data.quantity = quantity;

        fetch('cart_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(async response => {
            const result = await response.json();
            if (!response.ok && !result.message) {
                result.message = 'تعذر تنفيذ الطلب.';
            }
            return result;
        })
        .then(result => {
            if (result.success) {
                
                if (action === 'remove') {
                    // إخفاء الصف المحذوف
                    const row = document.getElementById(`row-${productId}`);
                    row.style.transition = 'all 0.5s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 500);
                    
                    // إذا لم يبق منتجات، نحدث الصفحة لإظهار حالة الفراغ
                    if (result.cart_total_items == 0) {
                        setTimeout(() => location.reload(), 600);
                    }
                } else if (action === 'update_quantity') {
                    // تحديث حقل الإدخال
                    document.getElementById(`qty-${productId}`).value = quantity;
                }

                // تحديث الأسعار والمجاميع في الصفحة بناءً على رد السيرفر
                updateCartTotals(result);
                
                // تحديث عداد السلة في الـ Header (إذا كان موجوداً)
                const headerCounter = document.getElementById('cart-counter');
                if (headerCounter) headerCounter.textContent = result.cart_total_items;

            } else {
                Swal.fire('خطأ', result.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('خطأ', 'حدثت مشكلة في الاتصال.', 'error');
        })
        .finally(() => {
            const currentRow = document.getElementById(`row-${productId}`);
            if (currentRow) {
                currentRow.dataset.updating = '0';
                currentRow.querySelectorAll('button').forEach(button => button.disabled = false);
            }
        });
    }

    // دالة لتحديث الأرقام في الصفحة دون إعادة التحميل
    function updateCartTotals(data) {
        // تحديث إجمالي السلة الكلي
        const grandTotals = document.querySelectorAll('#cart-grand-total-display, #final-total-display');
        grandTotals.forEach(el => el.textContent = data.grand_total);
        
        // تحديث عدد العناصر
        const itemsCount = document.getElementById('total-items-count');
        if (itemsCount) itemsCount.textContent = data.cart_total_items;

        // تحديث المجموع الفرعي لكل منتج (في حالة تحديث الكمية)
        if (data.subtotals) {
            for (const [pid, subtotal] of Object.entries(data.subtotals)) {
                const subEl = document.getElementById(`subtotal-${pid}`);
                if (subEl) subEl.textContent = subtotal;
            }
        }

        if (data.quantities) {
            for (const [pid, quantity] of Object.entries(data.quantities)) {
                const qtyEl = document.getElementById(`qty-${pid}`);
                if (qtyEl) qtyEl.value = quantity;
            }
        }
    }
</script>

<?php
require_once __DIR__ . '/admin_bootstrap.php';
require_once __DIR__ . '/../includes/cart_functions.php';
require_once __DIR__ . '/../includes/order_functions.php';

function format_egyptian_whatsapp_number(string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) === '20' && strlen($phone) === 12) return $phone;
    if (substr($phone, 0, 1) === '0' && strlen($phone) === 11) return '20' . substr($phone, 1);
    if (strlen($phone) === 10) return '20' . $phone;
    return $phone;
}

function restore_order_stock(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare(
        'UPDATE products p JOIN order_items oi ON oi.product_id = p.id
         SET p.stock_quantity = p.stock_quantity + oi.quantity
         WHERE oi.order_id = ?'
    );
    $stmt->execute([$orderId]);
}

function reserve_order_stock(PDO $pdo, int $orderId): void
{
    $itemsStmt = $pdo->prepare(
        'SELECT oi.product_id, oi.product_name, oi.quantity, p.stock_quantity
         FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ? FOR UPDATE'
    );
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($items)) {
        throw new RuntimeException('لا يمكن حجز مخزون لطلب بدون منتجات.');
    }

    foreach ($items as $item) {
        if ($item['product_id'] === null || $item['stock_quantity'] === null || (int) $item['stock_quantity'] < (int) $item['quantity']) {
            throw new RuntimeException('المخزون غير كافٍ لإعادة تنشيط الطلب.');
        }
    }

    $reserve = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');
    foreach ($items as $item) {
        $reserve->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        if ($reserve->rowCount() !== 1) {
            throw new RuntimeException('تغير المخزون أثناء تحديث الطلب.');
        }
    }
}

$admin_csrf_token = get_or_create_csrf_token('admin_orders_csrf_token');
$status_colors = order_status_colors();
$all_statuses = order_statuses();
$cancelled_status = 'ملغي';
$delivered_status = 'تم التسليم';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status_for_swal = 'error';

    try {
        if (!is_valid_csrf_token('admin_orders_csrf_token', $_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.');
        }

        $action = (string) ($_POST['action'] ?? '');
        $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$order_id || $order_id < 1) {
            throw new RuntimeException('رقم الطلب غير صالح.');
        }

        $pdo->beginTransaction();
        $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $orderStmt->execute([$order_id]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('الطلب غير موجود.');
        }

        if ($action === 'update_order' || $action === 'update_status') {
            if ((int) $order['is_archived'] === 1) {
                throw new RuntimeException('استرجع الطلب من الأرشيف قبل تعديله.');
            }

            $new_status = (string) ($_POST['status'] ?? '');
            if (!in_array($new_status, $all_statuses, true) || !can_transition_order_status($order['status'], $new_status)) {
                throw new RuntimeException('الانتقال المطلوب بين حالات الطلب غير مسموح.');
            }

            $shipping_input = $_POST['shipping_cost'] ?? null;
            if (!is_numeric($shipping_input)) {
                throw new RuntimeException('تكلفة الشحن غير صالحة.');
            }
            $shipping_cost = round((float) $shipping_input, 2);
            if ($shipping_cost < 0 || $shipping_cost > 999999.99) {
                throw new RuntimeException('تكلفة الشحن خارج الحد المسموح.');
            }

            if ($new_status === $cancelled_status && !(int) $order['stock_released']) {
                restore_order_stock($pdo, (int) $order_id);
                $order['stock_released'] = 1;
            } elseif ($order['status'] === $cancelled_status && $new_status !== $cancelled_status && (int) $order['stock_released']) {
                reserve_order_stock($pdo, (int) $order_id);
                $order['stock_released'] = 0;
            }

            $new_total = round((float) $order['subtotal_price'] + $shipping_cost, 2);
            $update = $pdo->prepare('UPDATE orders SET status = ?, shipping_cost = ?, total_price = ?, stock_released = ? WHERE id = ?');
            $update->execute([$new_status, $shipping_cost, $new_total, $order['stock_released'], $order_id]);

            if ($new_status !== $order['status'] || $shipping_cost !== (float) $order['shipping_cost']) {
                $notes = [];
                if ($new_status !== $order['status']) $notes[] = 'تم تغيير حالة الطلب';
                if ($shipping_cost !== (float) $order['shipping_cost']) $notes[] = 'تم تحديث الشحن من ' . number_format($order['shipping_cost'], 2) . ' إلى ' . number_format($shipping_cost, 2);
                $history = $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)');
                $history->execute([$order_id, $order['status'], $new_status, $_SESSION['admin_id'], implode(' - ', $notes)]);
            }

            $status_for_swal = 'order_updated';
        } elseif ($action === 'archive_order' || $action === 'delete_order') {
            if (!(int) $order['is_archived']) {
                if (!(int) $order['stock_released'] && $order['status'] !== $delivered_status) {
                    restore_order_stock($pdo, (int) $order_id);
                    $order['stock_released'] = 1;
                }
                $archive = $pdo->prepare('UPDATE orders SET is_archived = 1, archived_at = NOW(), archived_by = ?, stock_released = ? WHERE id = ?');
                $archive->execute([$_SESSION['admin_id'], $order['stock_released'], $order_id]);
                $history = $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)');
                $history->execute([$order_id, $order['status'], $order['status'], $_SESSION['admin_id'], 'تمت أرشفة الطلب']);
            }
            $status_for_swal = 'archived_success';
        } elseif ($action === 'restore_order') {
            if ((int) $order['is_archived']) {
                if ((int) $order['stock_released'] && !in_array($order['status'], [$cancelled_status, $delivered_status], true)) {
                    reserve_order_stock($pdo, (int) $order_id);
                    $order['stock_released'] = 0;
                }
                $restore = $pdo->prepare('UPDATE orders SET is_archived = 0, archived_at = NULL, archived_by = NULL, stock_released = ? WHERE id = ?');
                $restore->execute([$order['stock_released'], $order_id]);
                $history = $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)');
                $history->execute([$order_id, $order['status'], $order['status'], $_SESSION['admin_id'], 'تم استرجاع الطلب من الأرشيف']);
            }
            $status_for_swal = 'restored_success';
        } else {
            throw new RuntimeException('الإجراء المطلوب غير معروف.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Order management failed: ' . $e->getMessage());
        $_SESSION['admin_orders_error'] = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر تنفيذ العملية.';
    }

    $view = ($_POST['return_view'] ?? '') === 'archived' ? '&view=archived' : '';
    header('Location: manage_orders.php?status=' . urlencode($status_for_swal) . $view);
    exit;
}

$show_archived = ($_GET['view'] ?? '') === 'archived';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$archive_value = $show_archived ? 1 : 0;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE is_archived = ?');
$countStmt->execute([$archive_value]);
$total_orders = (int) $countStmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_orders / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$ordersStmt = $pdo->prepare('SELECT * FROM orders WHERE is_archived = ? ORDER BY order_date DESC LIMIT ? OFFSET ?');
$ordersStmt->bindValue(1, $archive_value, PDO::PARAM_INT);
$ordersStmt->bindValue(2, $per_page, PDO::PARAM_INT);
$ordersStmt->bindValue(3, $offset, PDO::PARAM_INT);
$ordersStmt->execute();
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$items_by_order = [];
$history_by_order = [];
if (!empty($orders)) {
    $order_ids = array_map('intval', array_column($orders, 'id'));
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $itemsStmt = $pdo->prepare("SELECT oi.order_id, oi.quantity, oi.price, COALESCE(oi.product_name, p.name, 'منتج محذوف') AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($placeholders) ORDER BY oi.id");
    $itemsStmt->execute($order_ids);
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $items_by_order[(int) $item['order_id']][] = $item;
    }

    $historyStmt = $pdo->prepare("SELECT h.order_id, h.old_status, h.new_status, h.note, h.created_at, a.full_name AS admin_name FROM order_status_history h LEFT JOIN admins a ON a.id = h.changed_by WHERE h.order_id IN ($placeholders) ORDER BY h.created_at DESC, h.id DESC");
    $historyStmt->execute($order_ids);
    foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $history) {
        $history_by_order[(int) $history['order_id']][] = $history;
    }
}

foreach ($orders as &$order) {
    $order['items'] = $items_by_order[(int) $order['id']] ?? [];
    $order['history'] = $history_by_order[(int) $order['id']] ?? [];
}
unset($order);

$admin_error = $_SESSION['admin_orders_error'] ?? null;
unset($_SESSION['admin_orders_error']);

require_once __DIR__ . '/admin_header.php';
?>

<div class="container-fluid content-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">إدارة الطلبات</h1>
        <a href="manage_orders.php<?= $show_archived ? '' : '?view=archived' ?>" class="btn btn-outline-secondary">
            <i class="fas <?= $show_archived ? 'fa-list' : 'fa-archive' ?> me-1"></i>
            <?= $show_archived ? 'الطلبات النشطة' : 'الأرشيف' ?>
        </a>
    </div>

    <?php if ($admin_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($admin_error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0"><?= $show_archived ? 'الطلبات المؤرشفة' : 'الطلبات الحالية' ?> (<?= $total_orders ?>)</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead><tr><th>الطلب</th><th>العميل</th><th>الهاتف</th><th>المنتجات</th><th>الشحن</th><th>الإجمالي</th><th>التاريخ</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">لا توجد طلبات في هذه القائمة.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <th>#<?= (int) $order['id'] ?></th>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><a href="https://wa.me/<?= htmlspecialchars(format_egyptian_whatsapp_number($order['customer_phone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($order['customer_phone']) ?></a></td>
                            <td><?= number_format($order['subtotal_price'], 2) ?></td>
                            <td><?= number_format($order['shipping_cost'], 2) ?></td>
                            <td><strong><?= number_format($order['total_price'], 2) ?> جنيه</strong></td>
                            <td><?= date('Y-m-d H:i', strtotime($order['order_date'])) ?></td>
                            <td><span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-info order-details-btn" data-bs-toggle="modal" data-bs-target="#orderDetailsModal" data-order='<?= htmlspecialchars(json_encode($order, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>'><i class="fa fa-eye"></i></button>
                                <form action="manage_orders.php" method="POST" class="d-inline archive-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="return_view" value="<?= $show_archived ? 'archived' : '' ?>">
                                    <input type="hidden" name="action" value="<?= $show_archived ? 'restore_order' : 'archive_order' ?>">
                                    <button type="submit" class="btn btn-sm <?= $show_archived ? 'btn-outline-success' : 'btn-outline-danger' ?>" title="<?= $show_archived ? 'استرجاع' : 'أرشفة' ?>"><i class="fas <?= $show_archived ? 'fa-undo' : 'fa-archive' ?>"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav><ul class="pagination justify-content-center mt-4">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="manage_orders.php?page=<?= $i ?><?= $show_archived ? '&view=archived' : '' ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                </ul></nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">تفاصيل الطلب #<span id="modal_order_id"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <h6>بيانات العميل</h6>
            <div class="card mb-3"><div class="card-body">
                <p><strong>الاسم:</strong> <span id="modal_customer_name"></span></p>
                <p><strong>الهاتف:</strong> <span id="modal_customer_phone"></span></p>
                <p><strong>العنوان:</strong> <span id="modal_customer_address"></span></p>
                <p><strong>الملاحظات:</strong> <span id="modal_notes"></span></p>
            </div></div>

            <h6>المنتجات</h6>
            <table class="table"><thead><tr><th>المنتج</th><th>الكمية</th><th>السعر</th></tr></thead><tbody id="modal_order_items"></tbody></table>
            <div class="text-end mb-4">
                <div>المنتجات: <strong id="modal_subtotal"></strong> جنيه</div>
                <div>الشحن: <strong id="modal_shipping"></strong> جنيه</div>
                <div class="fs-5">الإجمالي: <strong id="modal_total_price"></strong> جنيه</div>
            </div>

            <div id="order-update-section">
                <h6>تحديث الطلب</h6>
                <form id="update_order_form" action="manage_orders.php" method="POST">
                    <input type="hidden" name="action" value="update_order">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="order_id" id="form_order_id">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">الحالة</label><select class="form-select" name="status" id="form_status"><?php foreach ($all_statuses as $status): ?><option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">تكلفة الشحن</label><input type="number" min="0" step="0.01" class="form-control" name="shipping_cost" id="form_shipping_cost" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">حفظ التحديث</button>
                </form>
            </div>

            <hr><h6>سجل الطلب</h6><div id="modal_order_history" class="list-group"></div>
        </div>
    </div></div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const messages = {
        order_updated: ['success', 'تم تحديث الطلب بنجاح.'],
        archived_success: ['success', 'تمت أرشفة الطلب بدون حذف سجله.'],
        restored_success: ['success', 'تم استرجاع الطلب من الأرشيف.'],
        error: ['error', 'تعذر تنفيذ العملية.']
    };
    const status = new URLSearchParams(window.location.search).get('status');
    if (messages[status]) {
        Swal.fire({icon: messages[status][0], text: messages[status][1], timer: 2200, showConfirmButton: false});
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('status');
        window.history.replaceState(null, '', cleanUrl);
    }

    const transitions = <?= json_encode(allowed_order_transitions(), JSON_UNESCAPED_UNICODE) ?>;
    const modal = document.getElementById('orderDetailsModal');
    modal.addEventListener('show.bs.modal', function (event) {
        const order = JSON.parse(event.relatedTarget.getAttribute('data-order'));
        const setText = (selector, value) => modal.querySelector(selector).textContent = value ?? '';
        setText('#modal_order_id', order.id);
        setText('#modal_customer_name', order.customer_name);
        setText('#modal_customer_phone', order.customer_phone);
        setText('#modal_customer_address', order.customer_address);
        setText('#modal_notes', order.notes || 'لا توجد');
        setText('#modal_subtotal', Number(order.subtotal_price).toFixed(2));
        setText('#modal_shipping', Number(order.shipping_cost).toFixed(2));
        setText('#modal_total_price', Number(order.total_price).toFixed(2));

        const itemsBody = modal.querySelector('#modal_order_items');
        itemsBody.replaceChildren();
        (order.items || []).forEach(item => {
            const row = document.createElement('tr');
            [item.product_name, item.quantity, `${item.price} جنيه`].forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            itemsBody.appendChild(row);
        });
        if (!order.items || order.items.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 3;
            cell.textContent = 'لا توجد منتجات مسجلة.';
            row.appendChild(cell);
            itemsBody.appendChild(row);
        }

        modal.querySelector('#form_order_id').value = order.id;
        modal.querySelector('#form_shipping_cost').value = order.shipping_cost;
        const statusSelect = modal.querySelector('#form_status');
        statusSelect.value = order.status;
        const allowed = new Set([order.status, ...(transitions[order.status] || [])]);
        Array.from(statusSelect.options).forEach(option => option.disabled = !allowed.has(option.value));
        modal.querySelector('#order-update-section').classList.toggle('d-none', Number(order.is_archived) === 1);

        const historyList = modal.querySelector('#modal_order_history');
        historyList.replaceChildren();
        (order.history || []).forEach(entry => {
            const item = document.createElement('div');
            item.className = 'list-group-item';
            const title = document.createElement('div');
            title.className = 'fw-bold';
            title.textContent = entry.old_status && entry.old_status !== entry.new_status ? `${entry.old_status} ← ${entry.new_status}` : entry.new_status;
            const meta = document.createElement('small');
            meta.className = 'text-muted';
            meta.textContent = `${entry.created_at} — ${entry.admin_name || 'النظام'}${entry.note ? ` — ${entry.note}` : ''}`;
            item.append(title, meta);
            historyList.appendChild(item);
        });
    });

    document.querySelectorAll('.archive-form').forEach(form => {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const restoring = form.querySelector('[name="action"]').value === 'restore_order';
            Swal.fire({
                title: restoring ? 'استرجاع الطلب؟' : 'أرشفة الطلب؟',
                text: restoring ? 'سيعود الطلب إلى القائمة النشطة.' : 'لن يتم حذف بيانات الطلب ويمكن استرجاعه لاحقًا.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: restoring ? 'استرجاع' : 'أرشفة',
                cancelButtonText: 'إلغاء'
            }).then(result => { if (result.isConfirmed) form.submit(); });
        });
    });
});
</script>

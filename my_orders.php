<?php
require_once 'includes/auth_check.php';
require_once 'includes/db_connection.php';
require_once 'includes/order_functions.php';
$status_colors = order_status_colors();

$user_id = $_SESSION['user_id'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$countStmt->execute([$user_id]);
$totalPages = max(1, (int) ceil((int) $countStmt->fetchColumn() / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
    .orders-table th {
        font-weight: bold;
    }
    .table-responsive {
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border-radius: 15px;
        overflow: hidden;
    }
    .badge {
        font-size: 0.9rem;
    }
    @media (max-width: 768px) {
    .orders-table {
        font-size: 0.85rem;
    }
    .orders-table th, 
    .orders-table td {
        white-space: nowrap;
        padding: 0.4rem 0.5rem;
    }
    .orders-table .btn {
        font-size: 0.8rem;
        padding: 4px 6px;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

</style>
<div style="height: 50px;"></div>
<div class="container py-5">
    <h1 class="mb-4" style="font-family: var(--font-headings);">طلباتي</h1>

    <?php if (empty($orders)): ?>
        <div class="col-12 text-center">
            <div class="card p-5 border-0 shadow-sm" style="border-radius: 20px;">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h3 class="text-muted">لم تقم بأي طلبات بعد!</h3>
                <p>يبدو أنك لم تطلب أي منتج حتى الآن. ما رأيك في استكشاف مجموعتنا؟</p>
                <a href="products.php" class="btn btn-primary-custom mt-3 mx-auto" style="width: fit-content;">ابدأ التسوق الآن</a>
            </div>
        </div>
    <?php else: ?>
       <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0 orders-table">
        <thead class="table-light">
            <tr>
                <th>كود التتبع</th>
                <th>تاريخ الطلب</th>
                <th>الإجمالي</th>
                <th>الشحن</th>
                <th>الحالة</th>
                <th class="text-center">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><strong><?= htmlspecialchars($order['tracking_code']) ?></strong></td>
                <td><?= date('Y-m-d', strtotime($order['order_date'])) ?></td>
                <td><?= htmlspecialchars(number_format($order['total_price'], 2)) ?> جنيه</td>
                <td><?= htmlspecialchars(number_format($order['shipping_cost'], 2)) ?> جنيه</td>
                <td>
                    <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?>">
                        <?= htmlspecialchars($order['status']) ?>
                    </span>
                </td>
                <td class="text-center">
                    <a href="track_order.php?tracking_code=<?= htmlspecialchars($order['tracking_code']) ?>" 
                       class="btn btn-sm btn-outline-primary-custom">
                        <i class="fas fa-map-marker-alt me-1"></i> تتبع الطلب
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-4" aria-label="صفحات الطلبات"><ul class="pagination justify-content-center">
    <?php for ($n = 1; $n <= $totalPages; $n++): ?>
        <li class="page-item <?= $n === $page ? 'active' : '' ?>"><a class="page-link" href="my_orders.php?page=<?= $n ?>"><?= $n ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

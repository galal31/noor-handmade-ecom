<?php

require_once __DIR__ . '/admin_header.php';


try {
    
    $total_orders = $pdo->query("SELECT count(id) FROM orders WHERE is_archived = 0")->fetchColumn();

    
    $total_products = $pdo->query("SELECT count(id) FROM products")->fetchColumn();

    
    $total_users = $pdo->query("SELECT count(id) FROM users WHERE account_status = 'active'")->fetchColumn();

    
    $total_revenue = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'تم التسليم'")->fetchColumn();
    $total_revenue = $total_revenue ?: 0;

    
    $recent_orders = $pdo->query("SELECT * FROM orders WHERE is_archived = 0 ORDER BY order_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    
    $pending_orders = $pdo->query("SELECT count(id) FROM orders WHERE status = 'قيد المراجعة' AND is_archived = 0")->fetchColumn();

    

} catch (PDOException $e) {
    die("خطأ في جلب بيانات لوحة التحكم: " . $e->getMessage());
}


$status_colors = [
    'قيد المراجعة' => 'warning',
    'تم التأكيد' => 'info', 
    'جاري الشحن' => 'primary',
    'تم التسليم' => 'success',
    'ملغي' => 'danger'
];
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --info-gradient: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
    --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
}

.dashboard-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 20px;
}

.stat-card {
    border-radius: 20px;
    border: none;
    background: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.stat-card .card-body {
    padding: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-card .stat-content {
    flex: 1;
}

.stat-card .stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 5px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-card .stat-trend {
    font-size: 0.8rem;
    font-weight: 600;
}

.stat-card .stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    background: var(--primary-gradient);
    color: white;
}



.dashboard-header {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border-left: 5px solid #667eea;
}

.dashboard-title {
    font-size: 2rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 5px;
}

.dashboard-subtitle {
    color: #6c757d;
    font-size: 1.1rem;
}

.recent-orders-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border: none;
    overflow: hidden;
}

.recent-orders-card .card-header {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 20px 30px;
    font-weight: 700;
    font-size: 1.2rem;
}

.table th {
    border: none;
    font-weight: 700;
    color: #2c3e50;
    padding: 15px;
    background: #f8f9fa;
}

.table td {
    padding: 15px;
    vertical-align: middle;
    border-color: #f1f3f4;
}

.badge {
    padding: 8px 15px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
}

.view-all-btn {
    background: rgba(255,255,255,0.2);
    border: 2px solid white;
    color: white;
    border-radius: 10px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.view-all-btn:hover {
    background: white;
    color: #667eea;
    transform: translateY(-2px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card .card-body {
        padding: 20px;
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
    }
}
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="dashboard-title">لوحة التحكم الرئيسية</h1>
                <p class="dashboard-subtitle">نظرة عامة على أداء متجرك وإحصائيات المبيعات</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-muted">
                    <?= date('Y-m-d') ?> <i class="fas fa-calendar-alt ms-2"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="card-body">
                <div class="stat-content">
                    <div class="stat-title">إجمالي الإيرادات</div>
                    <div class="stat-number"><?= number_format($total_revenue, 2) ?> ج.م</div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-chart-line me-1"></i> إيرادات المسلمة
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>

        <div class="stat-card orders">
            <div class="card-body">
                <div class="stat-content">
                    <div class="stat-title">إجمالي الطلبات</div>
                    <div class="stat-number"><?= $total_orders ?></div>
                    <div class="stat-trend text-info">
                        <i class="fas fa-shopping-cart me-1"></i> جميع الطلبات
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
        </div>

        <div class="stat-card products">
            <div class="card-body">
                <div class="stat-content">
                    <div class="stat-title">المنتجات</div>
                    <div class="stat-number"><?= $total_products ?></div>
                    <div class="stat-trend text-warning">
                        <i class="fas fa-cubes me-1"></i> إجمالي المنتجات
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
        </div>

        <div class="stat-card users">
            <div class="card-body">
                <div class="stat-content">
                    <div class="stat-title">العملاء النشطين</div>
                    <div class="stat-number"><?= $total_users ?></div>
                    <div class="stat-trend text-success">
                        <i class="fas fa-user-check me-1"></i> عملاء نشطين
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="stat-card pending">
            <div class="card-body">
                <div class="stat-content">
                    <div class="stat-title">طلبات قيد الانتظار</div>
                    <div class="stat-number"><?= $pending_orders ?></div>
                    <div class="stat-trend text-warning">
                        <i class="fas fa-clock me-1"></i> تحتاج مراجعة
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        </div>

    <div class="recent-orders-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>أحدث الطلبات</span>
            <a href="manage_orders.php" class="view-all-btn">
                <i class="fas fa-list me-1"></i> عرض جميع الطلبات
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>اسم العميل</th>
                            <th>المبلغ الإجمالي</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    لا توجد طلبات حالياً
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <th>#<?= $order['id'] ?></th>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ms-3">
                                                <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?= number_format($order['total_price'], 2) ?> ج.م</strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar me-2 text-muted"></i>
                                        <?= date('Y-m-d', strtotime($order['order_date'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="manage_orders.php?action=view&id=<?= $order['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary rounded-pill">
                                            <i class="fas fa-eye me-1"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 

require_once __DIR__ . '/admin_footer.php'; 
?>

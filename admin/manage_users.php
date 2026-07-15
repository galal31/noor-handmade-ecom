<?php

require_once __DIR__ . '/admin_bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

$admin_csrf_token = security_csrf_token('admin_users_csrf_token');


$status_colors = [
    'pending_verification' => 'warning',
    'active' => 'success',
    'suspended' => 'danger'
];
$all_statuses = array_keys($status_colors);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $status_for_swal = 'error';

    try {
        if (!security_csrf_is_valid('admin_users_csrf_token', $_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.');
        }
        $submittedStatus = (string) ($_POST['account_status'] ?? 'active');
        if (in_array($action, ['add_user', 'edit_user'], true) && !in_array($submittedStatus, $all_statuses, true)) {
            throw new RuntimeException('حالة الحساب غير صالحة.');
        }
        switch ($action) {
            
            case 'add_user':
                if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) || strlen((string) ($_POST['password'] ?? '')) < 8) {
                    throw new RuntimeException('البريد غير صالح أو كلمة المرور أقل من 8 أحرف.');
                }
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, account_status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['full_name'], $_POST['email'], $password_hash, $_POST['account_status']]);
                $status_for_swal = 'added_success';
                break;

            
            case 'edit_user':
                $user_id = $_POST['user_id'];
                if (!filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('البريد الإلكتروني غير صالح.');
                }
                $sql_parts = [];
                $params = [];

                if (!empty($_POST['password'])) {
                    if (strlen((string) $_POST['password']) < 8) {
                        throw new RuntimeException('كلمة المرور يجب ألا تقل عن 8 أحرف.');
                    }
                    $sql_parts[] = "password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                $sql_parts[] = "full_name = ?";
                $params[] = $_POST['full_name'];
                $sql_parts[] = "email = ?";
                $params[] = $_POST['email'];
                $sql_parts[] = "account_status = ?";
                $params[] = $_POST['account_status'];
                $params[] = $user_id;

                $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $status_for_swal = 'updated_success';
                break;

            
            case 'delete_user':
                $stmt = $pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                $status_for_swal = 'suspended_success';
                break;
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        
        if ($e->getCode() == 23000) { 
            $status_for_swal = 'duplicate_email';
        }
    }
    
    header("Location: manage_users.php?status=" . $status_for_swal);
    exit;
}


$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$usersStmt = $pdo->prepare('SELECT id, full_name, email, account_status, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?');
$usersStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$usersStmt->bindValue(2, $offset, PDO::PARAM_INT);
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/admin_header.php';
?>

<div class="container-fluid content-container">
    <h1 class="mb-4">إدارة المستخدمين</h1>

    <div class="mb-4">
        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa fa-plus"></i> إضافة مستخدم جديد
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم الكامل</th>
                            <th>البريد الإلكتروني</th>
                            <th>الحالة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><span class="badge bg-<?= $status_colors[$user['account_status']] ?? 'secondary' ?>"><?= $user['account_status'] ?></span></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn" 
                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                            data-user='<?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="fa fa-edit"></i> تعديل
                                    </button>
                                    <form action="manage_users.php" method="POST" class="d-inline delete-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="تعليق حساب المستخدم"><i class="fas fa-user-slash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="manage_users.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-header"><h5 class="modal-title">إضافة مستخدم جديد</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="action" value="add_user">
            <div class="mb-3"><label class="form-label">الاسم الكامل</label><input type="text" class="form-control" name="full_name" required></div>
            <div class="mb-3"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control" name="email" required></div>
            <div class="mb-3"><label class="form-label">كلمة المرور</label><input type="password" class="form-control" name="password" required></div>
            <div class="mb-3"><label class="form-label">حالة الحساب</label>
                <select name="account_status" class="form-select" required>
                    <?php foreach ($all_statuses as $status_option): ?>
                    <option value="<?= $status_option ?>"><?= $status_option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button><button type="submit" class="btn btn-primary">حفظ المستخدم</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="manage_users.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-header"><h5 class="modal-title">تعديل المستخدم</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="mb-3"><label class="form-label">الاسم الكامل</label><input type="text" class="form-control" name="full_name" id="edit_full_name" required></div>
            <div class="mb-3"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control" name="email" id="edit_email" required></div>
            <div class="mb-3"><label class="form-label">كلمة مرور جديدة (اتركه فارغًا لعدم التغيير)</label><input type="password" class="form-control" name="password"></div>
            <div class="mb-3"><label class="form-label">حالة الحساب</label>
                <select name="account_status" class="form-select" id="edit_account_status" required>
                     <?php foreach ($all_statuses as $status_option): ?>
                    <option value="<?= $status_option ?>"><?= $status_option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button><button type="submit" class="btn btn-primary">حفظ التعديلات</button></div>
      </form>
    </div>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-4" aria-label="صفحات المستخدمين"><ul class="pagination justify-content-center">
    <?php for ($n = 1; $n <= $totalPages; $n++): ?>
        <li class="page-item <?= $n === $page ? 'active' : '' ?>"><a class="page-link" href="manage_users.php?page=<?= $n ?>"><?= $n ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php require_once __DIR__ . '/admin_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const messages = {
        'added_success': { icon: 'success', title: 'تم!', text: 'تمت إضافة المستخدم بنجاح.' },
        'updated_success': { icon: 'success', title: 'تم!', text: 'تم تحديث المستخدم بنجاح.' },
        'suspended_success': { icon: 'success', title: 'تم!', text: 'تم تعليق حساب المستخدم مع الاحتفاظ بسجلاته.' },
        'duplicate_email': { icon: 'error', title: 'خطأ!', text: 'هذا البريد الإلكتروني مسجل بالفعل.' },
        'error': { icon: 'error', title: 'خطأ!', text: 'حدث خطأ ما.' }
    };
    if (messages[status]) {
        Swal.fire({ icon: messages[status].icon, title: messages[status].title, text: messages[status].text, timer: 3000, showConfirmButton: false });
        window.history.replaceState(null, null, window.location.pathname);
    }

    
    const editModal = document.getElementById('editUserModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const userData = JSON.parse(button.getAttribute('data-user'));
        
        editModal.querySelector('#edit_user_id').value = userData.id;
        editModal.querySelector('#edit_full_name').value = userData.full_name;
        editModal.querySelector('#edit_email').value = userData.email;
        editModal.querySelector('#edit_account_status').value = userData.account_status;
    });

    
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function (event) {
            event.preventDefault(); 
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: "سيتم تعليق حساب المستخدم مع الاحتفاظ بسجلات الطلبات.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'نعم، علّق الحساب!',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            })
        });
    });
});
</script>

<?php
require_once __DIR__ . '/admin_bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

$admin_csrf_token = security_csrf_token('admin_catalog_csrf_token');



/**
 * Creates a URL-friendly slug from a string.
 */
function createSlug($text) {
    
    $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));

    
    $map = [
        'أ'=>'a','إ'=>'e','آ'=>'a','ا'=>'a','ب'=>'b','ت'=>'t','ث'=>'th','ج'=>'g',
        'ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','س'=>'s','ش'=>'sh',
        'ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'q',
        'ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','و'=>'w','ي'=>'y','ى'=>'a',
        'ة'=>'h','ء'=>'a','ئ'=>'a','ؤ'=>'w'
    ];

    
    $text = strtr($text, $map);

    
    $text = preg_replace('/\s+/u', '-', $text);

    
    $text = preg_replace('/[^a-zA-Z0-9\-]/u', '', $text);

    
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);

    
    $text = strtolower($text);

    
    return $text ?: 'category-' . uniqid();
}



/**
 * Ensures the generated slug is unique in the categories table.
 */
function generateUniqueSlugForCategory($pdo, $slug, $currentId = null) {
    $originalSlug = $slug;
    $counter = 1;
    $query = "SELECT COUNT(*) FROM categories WHERE slug = ?";
    $params = [$slug];
    if ($currentId !== null) {
        $query .= " AND id != ?";
        $params[] = $currentId;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    while ($stmt->fetchColumn() > 0) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
        $params[0] = $slug;
        $stmt->execute($params);
    }
    return $slug;
}


function upload_image($file_input_name, $upload_dir = '../images/categories/') {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_input_name]['tmp_name'];
        $validated = validate_uploaded_image($_FILES[$file_input_name]);
        $file_ext = $validated['extension'];
        $new_file_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
        $dest_path = $upload_dir . $new_file_name;
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_extensions) && move_uploaded_file($file_tmp_path, $dest_path)) {
            return $new_file_name;
        }
    }
    return null;
}

function delete_image($file_name, $upload_dir = '../images/categories/') {
    $safe_name = basename((string) $file_name);
    if ($safe_name && file_exists($upload_dir . $safe_name)) {
        unlink($upload_dir . $safe_name);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $status = 'error';

    try {
        if (!security_csrf_is_valid('admin_catalog_csrf_token', $_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.');
        }
        if ($action === 'add' && !empty($_POST['category_name'])) {
            $image_name = upload_image('category_image');
            
            
            $slug_base = !empty($_POST['slug']) ? $_POST['slug'] : $_POST['category_name'];
            $slug = createSlug($slug_base);
            $unique_slug = generateUniqueSlugForCategory($pdo, $slug);

            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, image) VALUES (?, ?, ?)");
            if ($stmt->execute([$_POST['category_name'], $unique_slug, $image_name])) {
                $status = 'added_success';
            }
        }
        elseif ($action === 'update' && !empty($_POST['category_name']) && !empty($_POST['category_id'])) {
            $category_id = $_POST['category_id'];
            
            
            $slug_base = !empty($_POST['slug']) ? $_POST['slug'] : $_POST['category_name'];
            $slug = createSlug($slug_base);
            $unique_slug = generateUniqueSlugForCategory($pdo, $slug, $category_id);

            $new_image_name = upload_image('category_image');
            if ($new_image_name) {
                $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                delete_image($stmt->fetchColumn());

                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, image = ? WHERE id = ?");
                $stmt->execute([$_POST['category_name'], $unique_slug, $new_image_name, $category_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                $stmt->execute([$_POST['category_name'], $unique_slug, $category_id]);
            }
            $status = 'updated_success';
        }
        elseif ($action === 'delete' && !empty($_POST['category_id'])) {
            $products_count = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $products_count->execute([$_POST['category_id']]);
            if ((int) $products_count->fetchColumn() > 0) {
                throw new RuntimeException('لا يمكن حذف قسم يحتوي على منتجات. انقل المنتجات أولًا.');
            }
            $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            delete_image($stmt->fetchColumn());
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            $status = 'deleted_success';
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $status = 'error';
    }
    header("Location: manage_categories.php?status=" . $status);
    exit;
}


$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$totalCategories = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$totalPages = max(1, (int) ceil($totalCategories / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$categoriesStmt = $pdo->prepare('SELECT * FROM categories ORDER BY id DESC LIMIT ? OFFSET ?');
$categoriesStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$categoriesStmt->bindValue(2, $offset, PDO::PARAM_INT);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/admin_header.php';
?>

<h1 class="mb-4">إدارة الأقسام</h1>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">إضافة قسم جديد</h5></div>
    <div class="card-body">
        <form action="manage_categories.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="add">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="category_name" class="form-label">اسم القسم</label>
                    <input type="text" class="form-control" id="category_name" name="category_name" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="add_slug" class="form-label">الرابط (Slug)</label>
                    <input type="text" class="form-control" id="add_slug" name="slug" dir="ltr">
                    <small class="form-text text-muted">اتركه فارغًا ليتم إنشاؤه تلقائيًا.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="category_image" class="form-label">صورة القسم</label>
                    <input type="file" class="form-control" id="category_image" name="category_image">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">إضافة القسم</button>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0">الأقسام الحالية</h5></div>
    <div class="card-body">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>الصورة</th>
                    <th>اسم القسم</th>
                    <th>الرابط (Slug)</th>
                    <th class="text-center">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td><img src="../images/categories/<?= htmlspecialchars($category['image'] ?? 'placeholder.svg') ?>" alt="<?= htmlspecialchars($category['name']) ?>" width="60" height="60" class="rounded object-fit-cover"></td>
                    <td><?= htmlspecialchars($category['name']) ?></td>
                    <td dir="ltr"><?= htmlspecialchars($category['slug']) ?></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editCategoryModal"
                                data-category-id="<?= $category['id'] ?>"
                                data-category-name="<?= htmlspecialchars($category['name']) ?>"
                                data-category-slug="<?= htmlspecialchars($category['slug']) ?>"
                                data-category-image="../images/categories/<?= htmlspecialchars($category['image'] ?? 'placeholder.svg') ?>">
                            <i class="fa fa-edit"></i> تعديل
                        </button>
                        <form action="manage_categories.php" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم؟ قد يؤثر هذا على المنتجات المرتبطة به.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i> حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">تعديل القسم</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="manage_categories.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-body">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="category_id" id="edit_category_id">
            
            <div class="text-center mb-3">
                <img id="edit_current_image" src="" alt="الصورة الحالية" class="rounded" width="100" height="100">
            </div>
            <div class="mb-3">
                <label for="edit_category_name" class="form-label">اسم القسم</label>
                <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
            </div>
            <div class="mb-3">
                <label for="edit_slug" class="form-label">الرابط (Slug)</label>
                <input type="text" class="form-control" id="edit_slug" name="slug" dir="ltr" required>
            </div>
            <div class="mb-3">
                <label for="edit_category_image" class="form-label">تغيير الصورة (اختياري)</label>
                <input type="file" class="form-control" id="edit_category_image" name="category_image">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
          <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-4" aria-label="صفحات الأقسام"><ul class="pagination justify-content-center">
    <?php for ($n = 1; $n <= $totalPages; $n++): ?>
        <li class="page-item <?= $n === $page ? 'active' : '' ?>"><a class="page-link" href="manage_categories.php?page=<?= $n ?>"><?= $n ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php require_once __DIR__ . '/admin_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const messages = {
        'added_success': { icon: 'success', title: 'نجاح!', text: 'تمت إضافة القسم بنجاح.' },
        'updated_success': { icon: 'success', title: 'نجاح!', text: 'تم تحديث القسم بنجاح.' },
        'deleted_success': { icon: 'success', title: 'نجاح!', text: 'تم حذف القسم بنجاح.' },
        'error': { icon: 'error', title: 'خطأ!', text: 'حدث خطأ ما. يرجى المحاولة مرة أخرى.' }
    };

    if (messages[status]) {
        Swal.fire({
            icon: messages[status].icon,
            title: messages[status].title,
            text: messages[status].text,
            timer: 3000,
            showConfirmButton: false
        });
        window.history.replaceState(null, null, window.location.pathname);
    }

    
    const editModal = document.getElementById('editCategoryModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        
        const categoryId = button.getAttribute('data-category-id');
        const categoryName = button.getAttribute('data-category-name');
        const categorySlug = button.getAttribute('data-category-slug'); 
        const categoryImage = button.getAttribute('data-category-image');

        editModal.querySelector('#edit_category_id').value = categoryId;
        editModal.querySelector('#edit_category_name').value = categoryName;
        editModal.querySelector('#edit_slug').value = categorySlug; 
        editModal.querySelector('#edit_current_image').src = categoryImage;
    });

    
    const categoryNameInput = document.getElementById('category_name');
    const addSlugInput = document.getElementById('add_slug');
    const createSlugJS = (text) => {
        return text.toString().toLowerCase().trim()
            .replace(/\s+/g, '-')
            .replace(/&/g, '-and-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-');
    };

    categoryNameInput.addEventListener('keyup', () => {
        addSlugInput.value = createSlugJS(categoryNameInput.value);
    });
});
</script>

<?php
/**
 * =================================================================
 * MANAGE PRODUCTS - COMPLETE SCRIPT
 * =================================================================
 * This script handles all logic for managing products in the admin panel.
 * It is structured to process all server-side logic first (POST requests)
 * before any HTML is rendered, ensuring AJAX requests work correctly.
 */





require_once __DIR__ . '/admin_bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

$admin_csrf_token = security_csrf_token('admin_catalog_csrf_token');



/**
 * Creates a URL-friendly slug from a string.
 * @param string $text The input string (e.g., product name).
 * @return string The generated slug.
 */
function createSlug($text) {
    $map = [
        'أ'=>'a','إ'=>'e','آ'=>'a','ا'=>'a','ب'=>'b','ت'=>'t','ث'=>'th','ج'=>'g',
        'ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','س'=>'s','ش'=>'sh',
        'ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'q',
        'ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','و'=>'w','ي'=>'y','ى'=>'a',
        'ة'=>'h','ء'=>'a','ئ'=>'a','ؤ'=>'w',' '=>'-'
    ];

    
    $text = strtr($text, $map);

    
    $text = preg_replace('/[^A-Za-z0-9\-]/u', '', $text);

    
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);

    
    $text = strtolower($text);

    return $text ?: 'product-' . uniqid();
}


/**
 * Ensures the generated slug is unique in the database.
 * @param PDO $pdo The database connection object.
 * @param string $slug The proposed slug.
 * @param int|null $currentId The ID of the current product (used during update to ignore itself).
 * @return string The unique slug.
 */
function generateUniqueSlug($pdo, $slug, $currentId = null) {
    $originalSlug = $slug;
    $counter = 1;
    $query = "SELECT COUNT(*) FROM products WHERE slug = ?";
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

/**
 * Handles uploading multiple product images.
 */
function upload_product_images($files, $product_id, $pdo) {
    $upload_dir = '../images/products/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $image_count = count($files['name']);
    $first_image_name = null;

    for ($i = 0; $i < $image_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file_tmp_path = $files['tmp_name'][$i];
            $file = [
                'name' => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i] ?? 0,
            ];
            $validated = validate_uploaded_image($file);
            $file_ext = $validated['extension'];

            if (in_array($file_ext, $allowed_extensions, true)) {
                $new_file_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
                $dest_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_name) VALUES (?, ?)");
                    $stmt->execute([$product_id, $new_file_name]);

                    if ($first_image_name === null) {
                        $first_image_name = $new_file_name;
                    }
                }
            }
        }
    }

    if ($first_image_name !== null) {
        $check_stmt = $pdo->prepare("SELECT main_image FROM products WHERE id = ?");
        $check_stmt->execute([$product_id]);
        $current_main = $check_stmt->fetch(PDO::FETCH_COLUMN);

        if (empty($current_main)) {
            $update_stmt = $pdo->prepare("UPDATE products SET main_image = ? WHERE id = ?");
            $update_stmt->execute([$first_image_name, $product_id]);
        }
    }
}

function validate_product_image_batch(array $files): void
{
    $names = array_values(array_filter($files['name'] ?? [], static fn($name) => $name !== ''));
    if (count($names) > 10) {
        throw new RuntimeException('يمكن رفع 10 صور كحد أقصى في المرة الواحدة.');
    }
    foreach ($names as $index => $name) {
        validate_uploaded_image([
            'name' => $name,
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ]);
    }
}

/**
 * Deletes all physical image files for a product.
 */
function delete_product_images($product_id, $pdo) {
    $upload_dir = '../images/products/';
    $stmt = $pdo->prepare("SELECT image_name FROM product_images WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($images as $image) {
        $file_path = $upload_dir . basename((string) $image);
        if ($image && file_exists($file_path)) {
            unlink($file_path);
        }
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $status = 'error'; 

    try {
        if (!security_csrf_is_valid('admin_catalog_csrf_token', $_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.');
        }
        
        if ($action === 'delete_image') {
            $image_id = $_POST['image_id'];
            $stmt = $pdo->prepare("SELECT product_id, image_name FROM product_images WHERE id = ?");
            $stmt->execute([$image_id]);
            $image_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image_data) {
                $file_path = '../images/products/' . basename((string) $image_data['image_name']);
                if (file_exists($file_path)) unlink($file_path);
                
                $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$image_id]);

                $prod_stmt = $pdo->prepare("SELECT main_image FROM products WHERE id = ?");
                $prod_stmt->execute([$image_data['product_id']]);
                if ($prod_stmt->fetchColumn() == $image_data['image_name']) {
                    $new_main = $pdo->query("SELECT image_name FROM product_images WHERE product_id = {$image_data['product_id']} ORDER BY id ASC LIMIT 1")->fetchColumn();
                    $pdo->prepare("UPDATE products SET main_image = ? WHERE id = ?")->execute([$new_main ?: null, $image_data['product_id']]);
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        elseif ($action === 'set_main_image') {
            $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $image_name = basename((string) ($_POST['image_name'] ?? ''));
            $belongs = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND image_name = ?');
            $belongs->execute([$product_id, $image_name]);
            if (!$product_id || !$image_name || !$belongs->fetchColumn()) {
                throw new RuntimeException('الصورة لا تتبع هذا المنتج.');
            }
            $stmt = $pdo->prepare("UPDATE products SET main_image = ? WHERE id = ?");
            $stmt->execute([$image_name, $product_id]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'image_name' => $image_name]);
            exit;
        }
        
        elseif ($action === 'add') {
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                validate_product_image_batch($_FILES['images']);
            }
            $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            if (!$category_id || $price === false || $price < 0 || trim((string) ($_POST['name'] ?? '')) === '') {
                throw new RuntimeException('بيانات المنتج غير صالحة.');
            }
            $slug_base = !empty($_POST['slug']) ? $_POST['slug'] : $_POST['name'];
            $slug = createSlug($slug_base);
            $unique_slug = generateUniqueSlug($pdo, $slug);
            
            $stock_quantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));
            $stmt = $pdo->prepare("INSERT INTO products (name, slug, description, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([trim($_POST['name']), $unique_slug, trim((string) $_POST['description']), $price, $stock_quantity, $category_id]);
            $product_id = $pdo->lastInsertId();
            
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                upload_product_images($_FILES['images'], $product_id, $pdo);
            }
            $status = 'added_success';
        } 
        
        elseif ($action === 'update') {
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                validate_product_image_batch($_FILES['images']);
            }
            $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $category_id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            if (!$product_id || !$category_id || $price === false || $price < 0 || trim((string) ($_POST['name'] ?? '')) === '') {
                throw new RuntimeException('بيانات المنتج غير صالحة.');
            }
            $slug_base = !empty($_POST['slug']) ? $_POST['slug'] : $_POST['name'];
            $slug = createSlug($slug_base);
            $unique_slug = generateUniqueSlug($pdo, $slug, $product_id);

            $stock_quantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));
            $stmt = $pdo->prepare("UPDATE products SET name = ?, slug = ?, description = ?, price = ?, stock_quantity = ?, category_id = ? WHERE id = ?");
            $stmt->execute([trim($_POST['name']), $unique_slug, trim((string) $_POST['description']), $price, $stock_quantity, $category_id, $product_id]);

            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                upload_product_images($_FILES['images'], $product_id, $pdo);
            }
            $status = 'updated_success';
        } 
        
        elseif ($action === 'delete') {
            $product_id = $_POST['product_id'];
            delete_product_images($product_id, $pdo); 
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $status = 'deleted_success';
        }
        
        elseif ($action === 'toggle_important') {
            $product_id = $_POST['product_id'];
            $stmt = $pdo->prepare("UPDATE products SET is_important = NOT is_important WHERE id = ?");
            $stmt->execute([$product_id]);
            $status = 'importance_updated';
        }

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $status = 'error';
        if (in_array($action, ['delete_image', 'set_main_image'], true)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    
    header("Location: manage_products.php?status=" . $status);
    exit;
}





$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$productsStmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT ? OFFSET ?');
$productsStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$productsStmt->bindValue(2, $offset, PDO::PARAM_INT);
$productsStmt->execute();
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);




require_once __DIR__ . '/admin_header.php';
?>

<h1 class="mb-4">إدارة المنتجات</h1>

<div class="mb-4">
    <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fa fa-plus"></i> إضافة منتج جديد
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0">المنتجات الحالية</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>الصورة الرئيسية</th>
                        <th>اسم المنتج</th>
                        <th>القسم</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="text-center text-muted">لا توجد منتجات حاليًا.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr class="<?= $product['is_important'] ? 'table-info' : '' ?>">
                            <td><img src="../images/products/<?= htmlspecialchars($product['main_image'] ?? 'placeholder.svg') ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="60" height="60" class="rounded object-fit-cover"></td>
                            <td>
                                <?= htmlspecialchars($product['name']) ?>
                                <?php if($product['is_important']): ?><span class="badge bg-warning text-dark">مهم</span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                            <td><?= htmlspecialchars(number_format($product['price'], 2)) ?> جنيه</td>
                            <td><span class="badge <?= (int) $product['stock_quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>"><?= (int) $product['stock_quantity'] ?></span></td>
                            <td class="text-center">
                                <form action="manage_products.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="toggle_important">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="تمييز كـ مهم/غير مهم"><i class="fa <?= $product['is_important'] ? 'fa-star' : 'fa-regular fa-star' ?>"></i></button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-bs-toggle="modal" data-bs-target="#editProductModal" data-product='<?= htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8') ?>'><i class="fa fa-edit"></i> تعديل</button>
                                <form action="manage_products.php" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟ سيتم حذف كل بياناته وصوره بشكل نهائي.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i> حذف</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5>إضافة منتج جديد</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="manage_products.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label for="add_name" class="form-label">اسم المنتج</label>
                <input type="text" class="form-control" id="add_name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="add_slug" class="form-label">الرابط (Slug)</label>
                <input type="text" class="form-control" id="add_slug" name="slug" dir="ltr">
                <small class="form-text text-muted"></small>
            </div>
            <div class="mb-3">
                <label for="add_description" class="form-label">الوصف</label>
                <textarea class="form-control" id="add_description" name="description" rows="4" required></textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="add_price" class="form-label">السعر</label>
                    <input type="number" step="0.01" class="form-control" id="add_price" name="price" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="add_stock_quantity" class="form-label">الكمية المتاحة</label>
                    <input type="number" min="0" step="1" class="form-control" id="add_stock_quantity" name="stock_quantity" value="0" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="add_category_id" class="form-label">القسم</label>
                    <select class="form-select" id="add_category_id" name="category_id" required>
                        <option value="" disabled selected>اختر قسم...</option>
                        <?php foreach($categories as $category): ?><option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="add_images" class="form-label">صور المنتج (يمكنك اختيار أكثر من صورة)</label>
                <input type="file" class="form-control" id="add_images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                 <small class="form-text text-muted">سيتم اعتبار أول صورة هي الصورة الرئيسية للمنتج.</small>
                <div id="add_images_preview" class="d-flex flex-wrap gap-3 mt-3"></div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
          <button type="submit" class="btn btn-primary">حفظ المنتج</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5>تعديل المنتج</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="editProductForm" action="manage_products.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($admin_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="product_id" id="edit_product_id">
            
            <div class="mb-3"><label for="edit_name" class="form-label">اسم المنتج</label><input type="text" class="form-control" id="edit_name" name="name" required></div>
            <div class="mb-3"><label for="edit_slug" class="form-label">الرابط (Slug)</label><input type="text" class="form-control" id="edit_slug" name="slug" dir="ltr" required></div>
            <div class="mb-3"><label for="edit_description" class="form-label">الوصف</label><textarea class="form-control" id="edit_description" name="description" rows="4" required></textarea></div>
            <div class="row">
                <div class="col-md-4 mb-3"><label for="edit_price" class="form-label">السعر</label><input type="number" step="0.01" class="form-control" id="edit_price" name="price" required></div>
                <div class="col-md-4 mb-3"><label for="edit_stock_quantity" class="form-label">الكمية المتاحة</label><input type="number" min="0" step="1" class="form-control" id="edit_stock_quantity" name="stock_quantity" required></div>
                <div class="col-md-4 mb-3"><label for="edit_category_id" class="form-label">القسم</label><select class="form-select" id="edit_category_id" name="category_id" required><?php foreach($categories as $category): ?><option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <hr>
            <div class="mb-3">
                <label class="form-label">الصور الحالية</label>
                <div id="current_images_gallery" class="d-flex flex-wrap" style="gap: 1.5rem;"></div>
            </div>
            <div class="mb-3">
                 <label for="edit_images" class="form-label">إضافة صور جديدة (اختياري)</label>
                 <input type="file" class="form-control" id="edit_images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                 <small class="form-text text-muted">الصور الجديدة ستُضاف إلى الصور الحالية.</small>
                 <div id="edit_images_preview" class="d-flex flex-wrap gap-3 mt-3"></div>
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
<nav class="mt-4" aria-label="صفحات المنتجات"><ul class="pagination justify-content-center">
    <?php for ($n = 1; $n <= $totalPages; $n++): ?>
        <li class="page-item <?= $n === $page ? 'active' : '' ?>"><a class="page-link" href="manage_products.php?page=<?= $n ?>"><?= $n ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require_once __DIR__ . '/admin_footer.php'; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {
    
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const messages = {
        'added_success': { icon: 'success', title: 'تم!', text: 'تمت إضافة المنتج بنجاح.' },
        'updated_success': { icon: 'success', title: 'تم!', text: 'تم تحديث المنتج بنجاح.' },
        'deleted_success': { icon: 'success', title: 'تم!', text: 'تم حذف المنتج بنجاح.' },
        'importance_updated': { icon: 'success', title: 'تم!', text: 'تم تحديث حالة أهمية المنتج.' },
        'error': { icon: 'error', title: 'خطأ!', text: 'حدث خطأ ما، يرجى المحاولة مرة أخرى.' }
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

    
    const editModal = document.getElementById('editProductModal');
    const galleryContainer = document.getElementById('current_images_gallery');

    const renderGallery = (images, mainImageName) => {
        galleryContainer.innerHTML = '';
        if (images.length > 0) {
            images.forEach(image => {
                const isMain = image.image_name === mainImageName;
                const wrapper = document.createElement('div');
                wrapper.className = 'position-relative text-center';
                wrapper.id = `image-wrapper-${image.id}`;
                wrapper.dataset.imageId = image.id;
                wrapper.dataset.imageName = image.image_name;

                const img = document.createElement('img');
                img.src = `../images/products/${image.image_name}`;
                img.style.width = '100px';
                img.style.height = '100px';
                img.className = 'rounded border p-1 object-fit-cover';
                img.style.borderWidth = '2px';
                img.style.borderColor = isMain ? 'var(--bs-primary)' : '#dee2e6';

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-danger btn-sm position-absolute top-0 start-100 translate-middle rounded-circle p-0 lh-1';
                deleteBtn.innerHTML = '<i class="fa fa-times" style="font-size: .75em;"></i>';
                deleteBtn.style.width = '22px';
                deleteBtn.style.height = '22px';
                deleteBtn.title = 'حذف الصورة';
                wrapper.appendChild(img);
                wrapper.appendChild(deleteBtn);

                if (isMain) {
                    const mainBadge = document.createElement('span');
                    mainBadge.className = 'badge bg-primary mt-1 d-block';
                    mainBadge.textContent = 'رئيسية';
                    wrapper.appendChild(mainBadge);
                } else {
                    const setMainBtn = document.createElement('button');
                    setMainBtn.type = 'button';
                    setMainBtn.className = 'btn btn-outline-success btn-sm mt-1';
                    setMainBtn.style.fontSize = '0.75rem';
                    setMainBtn.textContent = 'تعيين رئيسية';
                    wrapper.appendChild(setMainBtn);
                }
                galleryContainer.appendChild(wrapper);
            });
        } else {
            galleryContainer.innerHTML = '<p class="text-muted">لا توجد صور حالية لهذا المنتج.</p>';
        }
    };

    const setupMultipleImagePicker = (inputId, previewId) => {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        let selectedFiles = [];

        const fileKey = file => `${file.name}-${file.size}-${file.lastModified}`;

        const syncInputFiles = () => {
            const transfer = new DataTransfer();
            selectedFiles.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
        };

        const renderPreviews = () => {
            preview.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'position-relative text-center';
                item.style.width = '110px';

                const image = document.createElement('img');
                image.src = URL.createObjectURL(file);
                image.alt = file.name;
                image.className = 'rounded border object-fit-cover w-100';
                image.style.height = '100px';
                image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle p-0';
                removeButton.style.width = '24px';
                removeButton.style.height = '24px';
                removeButton.title = 'حذف الصورة من الاختيار';
                removeButton.innerHTML = '<i class="fa fa-times" style="font-size: .75em;"></i>';
                removeButton.addEventListener('click', () => {
                    selectedFiles.splice(index, 1);
                    syncInputFiles();
                    renderPreviews();
                });

                const name = document.createElement('small');
                name.className = 'd-block text-truncate mt-1';
                name.title = file.name;
                name.textContent = file.name;

                item.append(image, removeButton, name);
                preview.appendChild(item);
            });
        };

        input.addEventListener('change', () => {
            const existingKeys = new Set(selectedFiles.map(fileKey));
            Array.from(input.files).forEach(file => {
                if (file.type.startsWith('image/') && !existingKeys.has(fileKey(file))) {
                    selectedFiles.push(file);
                    existingKeys.add(fileKey(file));
                }
            });
            syncInputFiles();
            renderPreviews();
        });

        return {
            clear() {
                selectedFiles = [];
                input.value = '';
                preview.innerHTML = '';
            }
        };
    };

    setupMultipleImagePicker('add_images', 'add_images_preview');
    const editImagesPicker = setupMultipleImagePicker('edit_images', 'edit_images_preview');

    editModal.addEventListener('show.bs.modal', async function (event) {
        const button = event.relatedTarget;
        const productData = JSON.parse(button.getAttribute('data-product'));

        editModal.querySelector('#edit_product_id').value = productData.id;
        editModal.querySelector('#edit_name').value = productData.name;
        editModal.querySelector('#edit_slug').value = productData.slug; 
        editModal.querySelector('#edit_description').value = productData.description;
        editModal.querySelector('#edit_price').value = productData.price;
        editModal.querySelector('#edit_stock_quantity').value = productData.stock_quantity;
        editModal.querySelector('#edit_category_id').value = productData.category_id;
        editImagesPicker.clear();

        galleryContainer.innerHTML = '<p class="text-center p-3">جاري تحميل الصور...</p>';
        try {
            const response = await fetch(`get_product_images.php?product_id=${productData.id}`);
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            renderGallery(data.images, data.main_image);
        } catch (error) {
            galleryContainer.innerHTML = '<p class="text-danger">فشل في تحميل الصور.</p>';
            console.error('Error fetching images:', error);
        }
    });

    galleryContainer.addEventListener('click', function(event) {
        const productId = document.getElementById('edit_product_id').value;
        const deleteButton = event.target.closest('button.btn-danger');
        if (deleteButton) {
            const wrapper = deleteButton.closest('[data-image-id]');
            const imageId = wrapper.dataset.imageId;
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: "لن تتمكن من استعادة هذه الصورة!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'إلغاء',
                confirmButtonText: 'نعم، احذفها!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_image');
                    formData.append('image_id', imageId);
                    formData.append('csrf_token', <?= json_encode($admin_csrf_token) ?>);
                    fetch('manage_products.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            wrapper.remove();
                            Swal.fire('تم الحذف!', 'تم حذف الصورة بنجاح.', 'success');
                        } else { Swal.fire('خطأ!', 'فشل حذف الصورة.', 'error'); }
                    }).catch(err => Swal.fire('خطأ!', 'حدث خطأ بالشبكة.', 'error'));
                }
            });
        }

        const setMainButton = event.target.closest('button.btn-outline-success');
        if (setMainButton) {
            const wrapper = setMainButton.closest('[data-image-name]');
            const imageName = wrapper.dataset.imageName;
            const formData = new FormData();
            formData.append('action', 'set_main_image');
            formData.append('product_id', productId);
            formData.append('image_name', imageName);
            formData.append('csrf_token', <?= json_encode($admin_csrf_token) ?>);
            fetch('manage_products.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const allImages = Array.from(galleryContainer.querySelectorAll('[data-image-id]')).map(el => ({
                        id: el.dataset.imageId,
                        image_name: el.dataset.imageName
                    }));
                     renderGallery(allImages, data.image_name);
                } else { Swal.fire('خطأ!', 'فشل تعيين الصورة الرئيسية.', 'error'); }
            }).catch(err => Swal.fire('خطأ!', 'حدث خطأ بالشبكة.', 'error'));
        }
    });

    
    const addNameInput = document.getElementById('add_name');
    const addSlugInput = document.getElementById('add_slug');
    const createSlugJS = (text) => {
        
        return text.toString().toLowerCase().trim()
            .replace(/\s+/g, '-')           
            .replace(/&/g, '-and-')         
            .replace(/[^\w\-]+/g, '')       
            .replace(/\-\-+/g, '-');        
    };
    addNameInput.addEventListener('keyup', () => {
        addSlugInput.value = createSlugJS(addNameInput.value);
    });
});
</script>

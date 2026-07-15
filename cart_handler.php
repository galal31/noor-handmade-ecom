<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();

require_once 'includes/db_connection.php';
require_once 'includes/cart_functions.php';

header('Content-Type: application/json; charset=UTF-8');

function cart_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    cart_json_response(['success' => false, 'message' => 'الوصول غير مصرح به.'], 403);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_valid_csrf_token('cart_csrf_token', $csrfToken)) {
    cart_json_response(['success' => false, 'message' => 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى.'], 419);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data['action'])) {
    cart_json_response(['success' => false, 'message' => 'طلب غير صالح.'], 400);
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

normalize_cart($pdo, $_SESSION['cart']);

$action = (string) $data['action'];
$productId = isset($data['product_id']) ? filter_var($data['product_id'], FILTER_VALIDATE_INT) : false;
$response = ['success' => false, 'message' => 'الإجراء المطلوب غير معروف.'];
$responseStatus = 400;

if ($productId === false || $productId < 1) {
    cart_json_response(array_merge($response, cart_totals_response(normalize_cart($pdo, $_SESSION['cart']))), 422);
}

switch ($action) {
    case 'add':
        $quantity = isset($data['quantity']) ? filter_var($data['quantity'], FILTER_VALIDATE_INT) : 1;
        if ($quantity === false || $quantity < 1 || $quantity > CART_MAX_QUANTITY_PER_PRODUCT) {
            $response['message'] = 'الكمية المطلوبة غير صالحة.';
            $responseStatus = 422;
            break;
        }

        $products = get_cart_product_map($pdo, [$productId]);
        $product = $products[$productId] ?? null;
        if (!$product) {
            $response['message'] = 'هذا المنتج لم يعد متاحًا.';
            $responseStatus = 404;
            break;
        }

        $stock = (int) $product['stock_quantity'];
        $currentQuantity = (int) ($_SESSION['cart'][$productId]['quantity'] ?? 0);
        $newQuantity = $currentQuantity + $quantity;
        if ($stock < 1) {
            $response['message'] = 'هذا المنتج نفد من المخزون.';
            $responseStatus = 409;
            break;
        }
        if ($newQuantity > $stock || $newQuantity > CART_MAX_QUANTITY_PER_PRODUCT) {
            $response['message'] = 'الكمية المطلوبة أكبر من المخزون المتاح (' . min($stock, CART_MAX_QUANTITY_PER_PRODUCT) . ').';
            $responseStatus = 409;
            break;
        }

        $_SESSION['cart'][$productId] = ['quantity' => $newQuantity];
        $response = ['success' => true, 'message' => 'تمت إضافة المنتج للسلة!'];
        $responseStatus = 200;
        break;

    case 'update_quantity':
        $quantity = isset($data['quantity']) ? filter_var($data['quantity'], FILTER_VALIDATE_INT) : false;
        if ($quantity === false || $quantity < 1 || $quantity > CART_MAX_QUANTITY_PER_PRODUCT) {
            $response['message'] = 'الكمية المطلوبة غير صالحة.';
            $responseStatus = 422;
            break;
        }
        if (!isset($_SESSION['cart'][$productId])) {
            $response['message'] = 'المنتج غير موجود في السلة.';
            $responseStatus = 404;
            break;
        }

        $products = get_cart_product_map($pdo, [$productId]);
        $product = $products[$productId] ?? null;
        if (!$product) {
            unset($_SESSION['cart'][$productId]);
            $response['message'] = 'هذا المنتج لم يعد متاحًا وتم حذفه من السلة.';
            $responseStatus = 404;
            break;
        }
        if ($quantity > (int) $product['stock_quantity']) {
            $response['message'] = 'المخزون المتاح من هذا المنتج هو ' . (int) $product['stock_quantity'] . ' فقط.';
            $responseStatus = 409;
            break;
        }

        $_SESSION['cart'][$productId] = ['quantity' => $quantity];
        $response = ['success' => true, 'message' => 'تم تحديث الكمية.'];
        $responseStatus = 200;
        break;

    case 'remove':
        unset($_SESSION['cart'][$productId]);
        $response = ['success' => true, 'message' => 'تم حذف المنتج من السلة.'];
        $responseStatus = 200;
        break;
}

$cartData = normalize_cart($pdo, $_SESSION['cart']);
cart_json_response(array_merge($response, cart_totals_response($cartData)), $responseStatus);

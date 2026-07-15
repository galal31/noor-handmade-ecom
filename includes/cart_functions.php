<?php

const CART_MAX_QUANTITY_PER_PRODUCT = 99;

function get_cart_product_map(PDO $pdo, array $productIds, bool $lockForUpdate = false): array
{
    $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn ($id) => $id > 0)));
    if (empty($productIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $sql = "SELECT id, name, slug, price, stock_quantity, main_image FROM products WHERE id IN ($placeholders)";
    if ($lockForUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($productIds);

    $products = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
        $products[(int) $product['id']] = $product;
    }

    return $products;
}

function normalize_cart(PDO $pdo, array &$cart): array
{
    $result = [
        'items' => [],
        'cart_total_items' => 0,
        'grand_total_value' => 0.0,
        'subtotals' => [],
        'changes' => [],
    ];

    if (empty($cart)) {
        $cart = [];
        return $result;
    }

    $products = get_cart_product_map($pdo, array_keys($cart));
    $normalizedCart = [];

    foreach ($cart as $rawProductId => $item) {
        $productId = (int) $rawProductId;
        $product = $products[$productId] ?? null;

        if (!$product) {
            $result['changes'][] = 'تم حذف منتج غير متاح من السلة.';
            continue;
        }

        $stock = max(0, (int) $product['stock_quantity']);
        if ($stock === 0) {
            $result['changes'][] = 'تم حذف المنتج "' . $product['name'] . '" لنفاد المخزون.';
            continue;
        }

        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
        if ($quantity < 1) {
            $result['changes'][] = 'تم حذف كمية غير صالحة من السلة.';
            continue;
        }

        $allowedQuantity = min($stock, CART_MAX_QUANTITY_PER_PRODUCT);
        if ($quantity > $allowedQuantity) {
            $quantity = $allowedQuantity;
            $result['changes'][] = 'تم تعديل كمية "' . $product['name'] . '" لتناسب المخزون المتاح.';
        }

        $price = (float) $product['price'];
        $subtotal = round($price * $quantity, 2);
        $normalizedCart[$productId] = ['quantity' => $quantity];
        $result['items'][$productId] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ];
        $result['cart_total_items'] += $quantity;
        $result['grand_total_value'] += $subtotal;
        $result['subtotals'][$productId] = number_format($subtotal, 2, '.', '');
    }

    $cart = $normalizedCart;
    $result['grand_total_value'] = round($result['grand_total_value'], 2);
    return $result;
}

function cart_totals_response(array $cartData): array
{
    $quantities = [];
    foreach ($cartData['items'] as $productId => $item) {
        $quantities[$productId] = $item['quantity'];
    }

    return [
        'cart_total_items' => $cartData['cart_total_items'],
        'grand_total' => number_format($cartData['grand_total_value'], 2, '.', ''),
        'subtotals' => $cartData['subtotals'],
        'quantities' => $quantities,
        'cart_changes' => $cartData['changes'],
    ];
}

function get_or_create_csrf_token(string $sessionKey): string
{
    if (empty($_SESSION[$sessionKey]) || !is_string($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$sessionKey];
}

function is_valid_csrf_token(string $sessionKey, $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION[$sessionKey])
        && is_string($_SESSION[$sessionKey])
        && hash_equals($_SESSION[$sessionKey], $submittedToken);
}

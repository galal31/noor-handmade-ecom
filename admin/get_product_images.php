<?php
require_once __DIR__ . '/admin_bootstrap.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id === 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

try {
    
    $img_stmt = $pdo->prepare("SELECT id, image_name FROM product_images WHERE product_id = ? ORDER BY id ASC");
    $img_stmt->execute([$product_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

    $main_stmt = $pdo->prepare("SELECT main_image FROM products WHERE id = ?");
    $main_stmt->execute([$product_id]);
    $main_image = $main_stmt->fetch(PDO::FETCH_COLUMN);

    echo json_encode([
        'images' => $images,
        'main_image' => $main_image
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database query failed']);
}

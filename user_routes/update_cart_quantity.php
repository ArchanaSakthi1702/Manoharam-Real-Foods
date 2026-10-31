<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);

$productId = $data["product_id"] ?? null;
$cartItemId = $data["cart_item_id"] ?? null;
$quantity = isset($data["quantity"]) ? (float)$data["quantity"] : null;

if (($productId === null && $cartItemId === null) || $quantity === null) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "product_id (or cart_item_id) and quantity are required"
    ]);
    exit;
}

try {
    // Find user's cart
    $stmt = $pdo->prepare("
        SELECT id FROM carts
        WHERE customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user->id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        $stmt = $pdo->prepare("INSERT INTO carts (customer_id) VALUES (?)");
        $stmt->execute([$user->id]);
        $cartId = $pdo->lastInsertId();
    } else {
        $cartId = $cart["id"];
    }

    // Determine target product_id if cart_item_id passed
    if ($cartItemId) {
        $stmt = $pdo->prepare("
            SELECT product_id FROM cart_items
            WHERE id = ? AND cart_id = ?
        ");
        $stmt->execute([$cartItemId, $cartId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Cart item not found"
            ]);
            exit;
        }
        $productId = $item["product_id"];
    }

    // If quantity is 0 or less, remove item
    if ($quantity <= 0) {
        $stmt = $pdo->prepare("
            DELETE FROM cart_items
            WHERE cart_id = ? AND product_id = ?
        ");
        $stmt->execute([$cartId, $productId]);
        $message = "Item removed from cart";
    } else {
        // Verify product exists, is active, and is available for sale
        $stmt = $pdo->prepare("
            SELECT id, name, selling_price, is_subscription_only FROM products
            WHERE id = ? AND status = 'ACTIVE'
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Active product not found"
            ]);
            exit;
        }

        if (!empty($product['is_subscription_only'])) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "This product is only available for subscription and cannot be purchased directly"
            ]);
            exit;
        }

        // Upsert cart item quantity
        $stmt = $pdo->prepare("
            INSERT INTO cart_items (cart_id, product_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = ?
        ");
        $stmt->execute([$cartId, $productId, $quantity, $quantity]);
        $message = "Cart updated successfully";
    }

    // Return updated cart summary
    $stmt = $pdo->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            p.name,
            p.selling_price,
            ci.quantity,
            (ci.quantity * p.selling_price) AS item_total
        FROM cart_items ci
        INNER JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = ?
        ORDER BY ci.id DESC
    ");
    $stmt->execute([$cartId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += $item["item_total"];
    }

    echo json_encode([
        "success" => true,
        "message" => $message,
        "cart_id" => (int)$cartId,
        "total_amount" => number_format($totalAmount, 2, '.', ''),
        "total_items" => count($items),
        "cart_items" => $items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

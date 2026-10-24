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

if ($productId === null && $cartItemId === null) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "product_id or cart_item_id is required"
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
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Cart not found"
        ]);
        exit;
    }

    $cartId = $cart["id"];

    if ($cartItemId) {
        $stmt = $pdo->prepare("
            DELETE FROM cart_items
            WHERE id = ? AND cart_id = ?
        ");
        $stmt->execute([$cartItemId, $cartId]);
    } else {
        $stmt = $pdo->prepare("
            DELETE FROM cart_items
            WHERE product_id = ? AND cart_id = ?
        ");
        $stmt->execute([$productId, $cartId]);
    }

    // Get remaining items count and total
    $stmt = $pdo->prepare("
        SELECT
            COUNT(ci.id) AS item_count,
            COALESCE(SUM(ci.quantity * p.selling_price), 0) AS total_amount
        FROM cart_items ci
        INNER JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = ?
    ");
    $stmt->execute([$cartId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Item removed from cart successfully",
        "cart_id" => (int)$cartId,
        "remaining_items" => (int)$summary["item_count"],
        "total_amount" => number_format((float)$summary["total_amount"], 2, '.', '')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

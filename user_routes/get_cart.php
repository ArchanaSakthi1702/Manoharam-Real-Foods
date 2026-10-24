<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

try {

    // Find user's cart
    $stmt = $pdo->prepare("
        SELECT id
        FROM carts
        WHERE customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user->id]);

    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {

        echo json_encode([
            "success" => true,
            "cart_items" => [],
            "total_amount" => 0
        ]);

        exit;
    }

    // Get cart products
    $stmt = $pdo->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            p.name,
            p.category,
            p.unit,
            p.selling_price,
            p.description,
            p.image_url,
            ci.quantity,
            (ci.quantity * p.selling_price) AS item_total
        FROM cart_items ci
        INNER JOIN products p
            ON p.id = ci.product_id
        WHERE ci.cart_id = ?
        ORDER BY ci.id DESC
    ");

    $stmt->execute([$cart["id"]]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_amount = 0;

    foreach ($items as $item) {
        $total_amount += $item["item_total"];
    }

    echo json_encode([
        "success" => true,
        "cart_id" => $cart["id"],
        "total_amount" => number_format($total_amount, 2, '.', ''),
        "cart_items" => $items
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
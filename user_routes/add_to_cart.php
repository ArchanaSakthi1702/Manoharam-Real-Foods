<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);

$product_id = $data["product_id"] ?? null;
$quantity   = $data["quantity"] ?? 1;

if (!$product_id || $quantity <= 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "product_id and quantity are required"
    ]);

    exit;
}

try {

    // Check product exists
    $stmt = $pdo->prepare("
        SELECT id
        FROM products
        WHERE id = ?
        AND status = 'ACTIVE'
    ");

    $stmt->execute([$product_id]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product not found"
        ]);

        exit;
    }

    // Find cart
    $stmt = $pdo->prepare("
        SELECT id
        FROM carts
        WHERE customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user->id]);

    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {

        $stmt = $pdo->prepare("
            INSERT INTO carts (customer_id)
            VALUES (?)
        ");

        $stmt->execute([$user->id]);

        $cart_id = $pdo->lastInsertId();

    } else {

        $cart_id = $cart["id"];
    }

    // Check if product already exists in cart
    $stmt = $pdo->prepare("
        SELECT id, quantity
        FROM cart_items
        WHERE cart_id = ?
        AND product_id = ?
    ");

    $stmt->execute([$cart_id, $product_id]);

    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {

        $stmt = $pdo->prepare("
            UPDATE cart_items
            SET quantity = quantity + ?
            WHERE id = ?
        ");

        $stmt->execute([
            $quantity,
            $item["id"]
        ]);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO cart_items (
                cart_id,
                product_id,
                quantity
            )
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $cart_id,
            $product_id,
            $quantity
        ]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Product added to cart"
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
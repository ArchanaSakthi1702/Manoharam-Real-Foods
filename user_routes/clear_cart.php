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

try {
    $stmt = $pdo->prepare("
        SELECT id FROM carts
        WHERE customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user->id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cart) {
        $stmt = $pdo->prepare("
            DELETE FROM cart_items
            WHERE cart_id = ?
        ");
        $stmt->execute([$cart["id"]]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Cart cleared successfully"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

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

$order_id = $_GET["order_id"] ?? null;

if (!$order_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "order_id is required"
    ]);

    exit;
}

try {

    // Verify order belongs to user
    $stmt = $pdo->prepare("
        SELECT
            id,
            order_date,
            status,
            total_amount
        FROM orders
        WHERE id = ?
        AND customer_id = ?
    ");

    $stmt->execute([
        $order_id,
        $user->id
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Order not found"
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            oi.product_id,
            p.name,
            p.image_url,
            p.unit,
            oi.quantity,
            oi.price,
            (oi.quantity * oi.price) AS item_total
        FROM order_items oi
        INNER JOIN products p
            ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");

    $stmt->execute([$order_id]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "order" => $order,
        "items" => $items
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
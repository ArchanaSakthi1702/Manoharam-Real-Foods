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
require_once "../auth/admin_middleware.php";

requireAdmin();

try {

    $order_id = $_GET['id'] ?? null;

    if (!$order_id) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Order ID is required"
        ]);

        exit;
    }

    // =====================
    // ORDER + CUSTOMER
    // =====================
    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_date,
            o.status,
            o.total_amount,

            u.id AS customer_id,
            u.name AS customer_name,
            u.email,
            u.phone,
            u.address

        FROM orders o

        JOIN users u
            ON u.id = o.customer_id

        WHERE o.id = ?
        LIMIT 1
    ");

    $stmt->execute([$order_id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Order not found"
        ]);

        exit;
    }

    // =====================
    // ORDER ITEMS
    // =====================
    $stmt = $pdo->prepare("
        SELECT
            oi.id,
            oi.product_id,
            p.name,
            p.unit,
            p.image_url,

            oi.quantity,
            oi.price,

            (
                oi.quantity * oi.price
            ) AS line_total

        FROM order_items oi

        JOIN products p
            ON p.id = oi.product_id

        WHERE oi.order_id = ?
    ");

    $stmt->execute([$order_id]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "order" => [
            "id" => $order["id"],
            "order_date" => $order["order_date"],
            "status" => $order["status"],
            "total_amount" => $order["total_amount"],

            "customer" => [
                "id" => $order["customer_id"],
                "name" => $order["customer_name"],
                "email" => $order["email"],
                "phone" => $order["phone"],
                "address" => $order["address"]
            ],

            "items" => $items
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
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

$customerId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["customer_id"]) ? (int)$_GET["customer_id"] : null);

if (!$customerId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "customer_id is required"]);
    exit;
}

try {
    // 1. Customer Info
    $stmt = $pdo->prepare("
        SELECT id, name, email, phone, address, role, status, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Customer not found"]);
        exit;
    }

    // 2. Saved Addresses
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt->execute([$customerId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Past Orders
    $stmt = $pdo->prepare("
        SELECT id, order_date, status, total_amount, payment_method, payment_status, delivery_address
        FROM orders
        WHERE customer_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Subscriptions
    $stmt = $pdo->prepare("
        SELECT s.*, sp.name AS plan_name
        FROM subscriptions s
        INNER JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.customer_id = ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$customerId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "customer" => $customer,
        "addresses" => $addresses,
        "orders" => $orders,
        "subscriptions" => $subscriptions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

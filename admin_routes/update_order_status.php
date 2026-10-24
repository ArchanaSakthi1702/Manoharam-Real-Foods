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
require_once "../auth/admin_middleware.php";

requireAdmin();

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$order_id = $data["order_id"] ?? null;
$status = $data["status"] ?? null;

$allowedStatuses = [
    "CONFIRMED",
    "OUT_FOR_DELIVERY",
    "DELIVERED",
    "CANCELLED"
];

if (
    !$order_id ||
    !$status
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "order_id and status are required"
    ]);

    exit;
}

if (!in_array($status, $allowedStatuses)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid status"
    ]);

    exit;
}

try {

    $pdo->beginTransaction();

    // Check order
    $stmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM orders
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$order_id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    if (
        $order["status"] === "DELIVERED" ||
        $order["status"] === "CANCELLED"
    ) {
        throw new Exception(
            "Order can no longer be modified"
        );
    }

    // Update status
    $stmt = $pdo->prepare("
        UPDATE orders
        SET status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $status,
        $order_id
    ]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Order status updated successfully",
        "order" => [
            "id" => $order_id,
            "status" => $status
        ]
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
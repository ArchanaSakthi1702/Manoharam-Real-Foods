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

$orderId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["order_id"]) ? (int)$_GET["order_id"] : null);

if (!$orderId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "order_id is required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            customer_id,
            order_date,
            status,
            total_amount,
            delivery_address,
            payment_method,
            payment_status,
            cancellation_reason,
            cancelled_at
        FROM orders
        WHERE id = ? AND customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $user->id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Order not found"
        ]);
        exit;
    }

    $currentStatus = $order["status"];

    // Define timeline stages
    $allStages = [
        ["key" => "PLACED", "title" => "Order Placed", "desc" => "We have received your order."],
        ["key" => "CONFIRMED", "title" => "Order Confirmed", "desc" => "Order accepted and queued for packaging."],
        ["key" => "PREPARING", "title" => "Preparing & Packaging", "desc" => "Freshly preparing and packing jars."],
        ["key" => "OUT_FOR_DELIVERY", "title" => "Out for Delivery", "desc" => "Package is on the way to your delivery address."],
        ["key" => "DELIVERED", "title" => "Delivered", "desc" => "Package handed over to customer."]
    ];

    $timeline = [];
    $isCancelled = ($currentStatus === "CANCELLED");

    if ($isCancelled) {
        $timeline[] = [
            "title" => "Order Placed",
            "status" => "COMPLETED",
            "timestamp" => $order["order_date"]
        ];
        $timeline[] = [
            "title" => "Order Cancelled",
            "status" => "CANCELLED",
            "reason" => $order["cancellation_reason"],
            "timestamp" => $order["cancelled_at"]
        ];
    } else {
        $statusRanks = [
            "PENDING" => 1,
            "CONFIRMED" => 2,
            "PREPARING" => 3,
            "OUT_FOR_DELIVERY" => 4,
            "DELIVERED" => 5
        ];

        $currentRank = $statusRanks[$currentStatus] ?? 1;

        foreach ($allStages as $index => $stage) {
            $stageRank = $index + 1;
            $stageStatus = "PENDING";
            if ($stageRank < $currentRank) {
                $stageStatus = "COMPLETED";
            } elseif ($stageRank === $currentRank) {
                $stageStatus = "CURRENT";
            }

            $timeline[] = [
                "stage" => $stage["key"],
                "title" => $stage["title"],
                "description" => $stage["desc"],
                "status" => $stageStatus,
                "timestamp" => ($stageRank === 1 ? $order["order_date"] : null)
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "order_id" => (int)$order["id"],
        "current_status" => $currentStatus,
        "payment_method" => $order["payment_method"],
        "payment_status" => $order["payment_status"],
        "delivery_address" => $order["delivery_address"],
        "timeline" => $timeline
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

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

$subscription_id = $data["subscription_id"] ?? null;
$start_date = $data["start_date"] ?? null;
$end_date = $data["end_date"] ?? null;
$status = $data["status"] ?? null;

$allowedStatuses = [
    "PENDING",
    "ACTIVE",
    "COMPLETED",
    "CANCELLED"
];

if (!$subscription_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "subscription_id is required"
    ]);

    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT id
        FROM subscriptions
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$subscription_id]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Subscription not found"
        ]);

        exit;
    }

    $fields = [];
    $params = [];

    if (!empty($start_date)) {

        $fields[] = "start_date = ?";
        $params[] = $start_date;
    }

    if (!empty($end_date)) {

        $fields[] = "end_date = ?";
        $params[] = $end_date;
    }

    if (!empty($status)) {

        if (!in_array($status, $allowedStatuses)) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Invalid status"
            ]);

            exit;
        }

        $fields[] = "status = ?";
        $params[] = $status;
    }

    if (empty($fields)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "No fields to update"
        ]);

        exit;
    }

    $params[] = $subscription_id;

    $sql = "
        UPDATE subscriptions
        SET " . implode(", ", $fields) . "
        WHERE id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        "success" => true,
        "message" => "Subscription updated successfully"
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
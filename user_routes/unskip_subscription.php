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

$subscription_id = $data["subscription_id"] ?? null;
$skip_date = trim($data["skip_date"] ?? "");

if (!$subscription_id || empty($skip_date)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "subscription_id and skip_date are required"
    ]);
    exit;
}

try {
    // 1. Verify subscription
    $stmt = $pdo->prepare("
        SELECT id, customer_id, status
        FROM subscriptions
        WHERE id = ? AND customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$subscription_id, $user->id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception("Subscription not found");
    }

    if (!in_array($subscription["status"], ["ACTIVE", "APPROVED", "PENDING"])) {
        throw new Exception("Cannot unskip on a subscription that is " . strtolower($subscription["status"]));
    }

    // 2. Validate date
    $currentDate = date("Y-m-d");
    if ($skip_date < $currentDate) {
        throw new Exception("Cannot unskip a date in the past");
    }

    // 3. Find skip entry
    $stmt = $pdo->prepare("
        SELECT id, skip_type
        FROM subscription_skips
        WHERE subscription_id = ? AND skip_date = ?
        LIMIT 1
    ");
    $stmt->execute([$subscription_id, $skip_date]);
    $skipRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$skipRecord) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "No skip found for the specified date"
        ]);
        exit;
    }

    if ($skipRecord["skip_type"] === "SATURDAY" || $skipRecord["skip_type"] === "SUNDAY") {
        throw new Exception("Cannot unskip recurring weekend days configured in your subscription plan settings");
    }

    // 4. Delete skip
    $deleteStmt = $pdo->prepare("
        DELETE FROM subscription_skips
        WHERE id = ?
    ");
    $deleteStmt->execute([$skipRecord["id"]]);

    echo json_encode([
        "success" => true,
        "message" => "Delivery date unskipped successfully. Delivery resumed for {$skip_date}.",
        "subscription_id" => (int)$subscription_id,
        "date" => $skip_date
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

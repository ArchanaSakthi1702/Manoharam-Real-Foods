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
$reason = trim($data["reason"] ?? "");

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
        SELECT id, customer_id, status
        FROM subscriptions
        WHERE id = ? AND customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$subscription_id, $user->id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        throw new Exception("Subscription not found");
    }

    if ($sub["status"] === "PAUSED") {
        throw new Exception("Subscription is already paused");
    }

    if ($sub["status"] !== "ACTIVE" && $sub["status"] !== "APPROVED") {
        throw new Exception("Only active subscriptions can be paused");
    }

    $updateStmt = $pdo->prepare("
        UPDATE subscriptions
        SET status = 'PAUSED'
        WHERE id = ?
    ");
    $updateStmt->execute([$subscription_id]);

    echo json_encode([
        "success" => true,
        "message" => "Subscription paused successfully",
        "subscription_id" => (int)$subscription_id,
        "status" => "PAUSED",
        "reason" => $reason ?: null
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

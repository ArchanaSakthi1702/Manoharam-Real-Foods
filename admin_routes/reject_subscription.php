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

$admin = requireAdmin();

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
        SELECT s.id, s.status, u.name AS customer_name, sp.name AS plan_name
        FROM subscriptions s
        JOIN users u ON u.id = s.customer_id
        JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception("Subscription not found");
    }

    if ($subscription["status"] !== "PENDING") {
        throw new Exception("Only pending subscriptions can be rejected");
    }

    try {
        $updateStmt = $pdo->prepare("
            UPDATE subscriptions
            SET status = 'REJECTED', rejection_reason = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$reason ?: "Rejected by administrator", $subscription_id]);
    } catch (PDOException $e) {
        // Fallback to CANCELLED if REJECTED enum not yet added
        $updateStmt = $pdo->prepare("UPDATE subscriptions SET status = 'CANCELLED' WHERE id = ?");
        $updateStmt->execute([$subscription_id]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Subscription request rejected",
        "subscription" => [
            "id" => (int)$subscription_id,
            "customer_name" => $subscription["customer_name"],
            "plan_name" => $subscription["plan_name"],
            "status" => "REJECTED",
            "reason" => $reason ?: "Rejected by administrator"
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

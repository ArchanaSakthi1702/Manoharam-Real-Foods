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

$admin=requireAdmin();

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$subscription_id = $data["subscription_id"] ?? null;

if (!$subscription_id) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "subscription_id is required"
    ]);

    exit;
}

try {

    $pdo->beginTransaction();

    // Check subscription
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.customer_id,
            s.plan_id,
            s.status,
            u.name AS customer_name,
            sp.name AS plan_name
        FROM subscriptions s
        JOIN users u
            ON u.id = s.customer_id
        JOIN subscription_plans sp
            ON sp.id = s.plan_id
        WHERE s.id = ?
        LIMIT 1
    ");

    $stmt->execute([$subscription_id]);

    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception("Subscription not found");
    }

    if ($subscription["status"] !== "PENDING") {
        throw new Exception(
            "Only pending subscriptions can be approved"
        );
    }

    // Approve subscription
    $stmt = $pdo->prepare("
        UPDATE subscriptions
        SET
            status = 'ACTIVE',
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ?  AND status = 'PENDING'
    ");

    $stmt->execute([
        $admin->id,
        $subscription_id
    ]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Subscription approved successfully",
        "subscription" => [
            "id" => $subscription["id"],
            "customer_name" => $subscription["customer_name"],
            "plan_name" => $subscription["plan_name"],
            "status" => "APPROVED"
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
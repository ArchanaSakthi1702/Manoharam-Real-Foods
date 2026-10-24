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

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$subscription_id = $data["subscription_id"] ?? null;
$skip_date = $data["skip_date"] ?? null;
$reason = trim($data["reason"] ?? "");

if (!$subscription_id || !$skip_date) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "subscription_id and skip_date are required"
    ]);

    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            sp.daily_rate
        FROM subscriptions s
        JOIN subscription_plans sp
            ON sp.id = s.plan_id
        WHERE s.id = ?
        AND s.customer_id = ?
    ");

    $stmt->execute([
        $subscription_id,
        $user->id
    ]);

    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception(
            "Subscription not found"
        );
    }

    $refund_amount =
        $subscription["daily_rate"];

    $stmt = $pdo->prepare("
        INSERT INTO subscription_skips (
            subscription_id,
            skip_date,
            refund_amount,
            reason
        )
        VALUES (
            ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $subscription_id,
        $skip_date,
        $refund_amount,
        $reason ?: null
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Day skipped successfully",
        "refund_amount" => $refund_amount
    ]);

} catch (PDOException $e) {

    if ($e->getCode() == 23000) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "This date is already skipped"
        ]);

        exit;
    }

    throw $e;

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

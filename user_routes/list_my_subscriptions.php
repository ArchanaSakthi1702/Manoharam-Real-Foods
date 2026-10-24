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

try {

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.plan_id,
            s.start_date,
            s.end_date,
            s.status,
            sp.name AS plan_name
        FROM subscriptions s
        INNER JOIN subscription_plans sp
            ON sp.id = s.plan_id
        WHERE s.customer_id = ?
        AND s.status IN ('PENDING', 'ACTIVE')
        ORDER BY s.id DESC
    ");

    $stmt->execute([$user->id]);

    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subscriptions as &$subscription) {

        $subscription["id"] = (int)$subscription["id"];
        $subscription["plan_id"] = (int)$subscription["plan_id"];
    }

    unset($subscription);

    echo json_encode([
        "success" => true,
        "subscriptions" => $subscriptions
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}
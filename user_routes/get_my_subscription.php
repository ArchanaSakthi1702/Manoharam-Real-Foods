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

            sp.name AS plan_name,
            sp.price_per_week,
            sp.description AS plan_description

        FROM subscriptions s
        INNER JOIN subscription_plans sp
            ON sp.id = s.plan_id

        WHERE s.customer_id = ?
        AND s.status IN ('PENDING', 'ACTIVE')
        ORDER BY s.id DESC
    ");

    $stmt->execute([$user->id]);

    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) {

        echo json_encode([
            "success" => true,
            "subscriptions" => []
        ]);

        exit;
    }

    // Products + skip history for each subscription individually — a
    // customer can now hold more than one live subscription at once, so
    // this can no longer assume there's a single row to enrich.
    foreach ($subscriptions as &$subscription) {

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.name,
                p.category,
                p.quantity,
                p.unit,
                p.selling_price,
                p.image_url,
                spp.delivery_order
            FROM subscription_plan_products spp
            INNER JOIN products p
                ON p.id = spp.product_id
            WHERE spp.plan_id = ?
            ORDER BY spp.delivery_order ASC
        ");

        $stmt->execute([
            $subscription["plan_id"]
        ]);

        $subscription["products"] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT
                skip_date,
                refund_amount,
                reason
            FROM subscription_skips
            WHERE subscription_id = ?
            ORDER BY skip_date ASC
        ");

        $stmt->execute([
            $subscription["id"]
        ]);

        $subscription["skips"] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        "message" => $e->getMessage()
    ]);
}
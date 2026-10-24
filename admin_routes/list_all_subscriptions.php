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
require_once "../auth/admin_middleware.php";

requireAdmin();

try {

    $cursor = $_GET['cursor'] ?? null;
    $limit = (int)($_GET['limit'] ?? 10);

    if ($limit <= 0) {
        $limit = 10;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $sql = "
        SELECT
            s.id,
            s.status,
            s.start_date,
            s.end_date,

            u.name AS customer_name,

            sp.name AS plan_name

        FROM subscriptions s

        INNER JOIN users u
            ON u.id = s.customer_id

        INNER JOIN subscription_plans sp
            ON sp.id = s.plan_id
    ";

    $params = [];

    if (!empty($cursor)) {

        $sql .= "
            WHERE s.id < ?
        ";

        $params[] = $cursor;
    }

    $fetchLimit = $limit + 1;

    $sql .= "
        ORDER BY s.id DESC
        LIMIT $fetchLimit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more = count($subscriptions) > $limit;

    if ($has_more) {
        array_pop($subscriptions);
    }

    $next_cursor = null;

    if ($has_more && !empty($subscriptions)) {

        $lastSubscription = end($subscriptions);

        $next_cursor = $lastSubscription["id"];
    }

    echo json_encode([
        "success" => true,
        "subscriptions" => $subscriptions,
        "pagination" => [
            "limit" => $limit,
            "has_more" => $has_more,
            "next_cursor" => $next_cursor
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
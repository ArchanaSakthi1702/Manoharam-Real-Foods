<?php

header("Content-Type: application/json");

require_once __DIR__ . "/cors.php";
require_once "db.php";

try {

    $cursor = $_GET["cursor"] ?? null;
    $limit = (int)($_GET["limit"] ?? 10);
    $category = $_GET["category"] ?? null;

    if ($limit <= 0) {
        $limit = 10;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $sql = "
        SELECT
            id,
            name,
            category,
            price_per_week
        FROM subscription_plans
        WHERE status = 'ACTIVE'
    ";

    $params = [];

    if ($category !== null) {

        $category = strtoupper(trim($category));

        if (in_array($category, ["VEG", "NON_VEG"])) {
            $sql .= " AND category = ? ";
            $params[] = $category;
        }
    }

    if (!empty($cursor)) {
        $sql .= " AND id < ? ";
        $params[] = (int)$cursor;
    }

    $fetchLimit = $limit + 1;

    $sql .= "
        ORDER BY id DESC
        LIMIT $fetchLimit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more = count($plans) > $limit;

    if ($has_more) {
        array_pop($plans);
    }

    foreach ($plans as &$plan) {
        $plan["id"] = (int)$plan["id"];
        $plan["price_per_week"] = (float)$plan["price_per_week"];
    }

    unset($plan);

    $next_cursor = null;

    if ($has_more && !empty($plans)) {
        $lastPlan = end($plans);
        $next_cursor = (int)$lastPlan["id"];
    }

    echo json_encode([
        "success" => true,
        "plans" => $plans,
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
        "message" => "Something went wrong"
    ]);
}
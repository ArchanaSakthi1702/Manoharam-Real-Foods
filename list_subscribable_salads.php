<?php

header("Content-Type: application/json");

require_once __DIR__ . "/cors.php";
require_once "db.php";
require_once "auth/auth_middleware.php";

try {

    $user = authenticate();
    $userId = $user->id;

    $cursor = $_GET["cursor"] ?? null;
    $limit = (int)($_GET["limit"] ?? 10);

    if ($limit <= 0) {
        $limit = 10;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $sql = "
        SELECT
            sp.*
        FROM subscription_plans sp
        WHERE sp.status = 'ACTIVE'
        AND NOT EXISTS (
            SELECT 1
            FROM subscriptions s
            WHERE s.customer_id = ?
            AND s.plan_id = sp.id
            AND s.status IN (
                'PENDING',
                'APPROVED',
                'ACTIVE'
            )
        )
    ";

    $params = [$userId];

    if (!empty($cursor)) {

        $sql .= "
            AND sp.id < ?
        ";

        $params[] = $cursor;
    }

    $fetchLimit = $limit + 1;

    $sql .= "
        ORDER BY sp.id DESC
        LIMIT $fetchLimit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($plans) > $limit;

    if ($hasMore) {
        array_pop($plans);
    }

    if (!empty($plans)) {

        $planIds = array_column($plans, "id");

        $placeholders = implode(
            ",",
            array_fill(0, count($planIds), "?")
        );

        $productStmt = $pdo->prepare("
            SELECT
                spp.plan_id,
                p.*
            FROM subscription_plan_products spp
            INNER JOIN products p
                ON p.id = spp.product_id
            WHERE spp.plan_id IN ($placeholders)
            ORDER BY p.name ASC
        ");

        $productStmt->execute($planIds);

        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

        $productsByPlan = [];

        foreach ($products as $product) {

            $planId = $product["plan_id"];

            unset($product["plan_id"]);

            if (isset($product["id"])) {
                $product["id"] = (int)$product["id"];
            }

            $productsByPlan[$planId][] = $product;
        }

        foreach ($plans as &$plan) {

            $plan["id"] = (int)$plan["id"];
            $plan["duration_days"] = (int)$plan["duration_days"];
            $plan["price"] = (float)$plan["price"];

            if ($plan["daily_rate"] !== null) {
                $plan["daily_rate"] = (float)$plan["daily_rate"];
            }

            $plan["products"] =
                $productsByPlan[$plan["id"]] ?? [];
        }

        unset($plan);
    }

    $nextCursor = null;

    if ($hasMore && !empty($plans)) {

        $lastPlan = end($plans);

        $nextCursor = $lastPlan["id"];
    }

    echo json_encode([
        "success" => true,
        "plans" => $plans,
        "pagination" => [
            "limit" => $limit,
            "has_more" => $hasMore,
            "next_cursor" => $nextCursor
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
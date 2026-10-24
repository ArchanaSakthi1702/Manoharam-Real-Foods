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

// This mirrors create_subscription.php's schema: one subscription_plans row
// can link to MULTIPLE products (via subscription_plan_products, ordered by
// delivery_order), priced with price_per_week — NOT the old single-product /
// duration_days / total-price model. The frontend Plans tab must render
// plan.products (array) rather than a single plan.product_name.

try {

    $cursor = $_GET['cursor'] ?? null;
    $limit = (int)($_GET['limit'] ?? 20);

    if ($limit <= 0) {
        $limit = 20;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $sql = "
        SELECT
            sp.id,
            sp.name,
            sp.price_per_week,
            sp.description,
            sp.status
        FROM subscription_plans sp
    ";

    $params = [];

    if (!empty($cursor)) {
        $sql .= " WHERE sp.id < ? ";
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

    $has_more = count($plans) > $limit;

    if ($has_more) {
        array_pop($plans);
    }

    $next_cursor = null;

    if ($has_more && !empty($plans)) {
        $lastPlan = end($plans);
        $next_cursor = $lastPlan["id"];
    }

    // Attach the linked products (in delivery order) to each plan.
    if (!empty($plans)) {

        $planIds = array_column($plans, "id");
        $placeholders = implode(",", array_fill(0, count($planIds), "?"));

        $productsStmt = $pdo->prepare("
            SELECT
                spp.plan_id,
                spp.delivery_order,
                p.id,
                p.name,
                p.image_url
            FROM subscription_plan_products spp
            INNER JOIN products p
                ON p.id = spp.product_id
            WHERE spp.plan_id IN ($placeholders)
            ORDER BY spp.plan_id, spp.delivery_order ASC
        ");

        $productsStmt->execute($planIds);
        $productRows = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

        $productsByPlan = [];

        foreach ($productRows as $row) {
            $planId = $row["plan_id"];

            if (!isset($productsByPlan[$planId])) {
                $productsByPlan[$planId] = [];
            }

            $productsByPlan[$planId][] = [
                "id" => (int)$row["id"],
                "name" => $row["name"],
                "image_url" => $row["image_url"],
                "delivery_order" => (int)$row["delivery_order"],
            ];
        }

        foreach ($plans as &$plan) {
            $plan["price_per_week"] = (float)$plan["price_per_week"];
            $plan["products"] = $productsByPlan[$plan["id"]] ?? [];
        }
        unset($plan);
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
        "message" => $e->getMessage()
    ]);
}

<?php

header("Content-Type: application/json");

require_once __DIR__ . "/cors.php";
require_once "db.php";

try {

    $planId = (int)($_GET["plan_id"] ?? 0);

    if ($planId <= 0) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid plan_id"
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            category,
            description,
            price_per_week
        FROM subscription_plans
        WHERE id = ?
        AND status = 'ACTIVE'
    ");

    $stmt->execute([$planId]);

    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Plan not found"
        ]);

        exit;
    }

    $plan["id"] = (int)$plan["id"];
    $plan["price_per_week"] = (float)$plan["price_per_week"];

    $productStmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.quantity,
            p.unit,
            p.image_url,
            p.is_subscription_only,
            spp.delivery_order,

            rn.calories,
            rn.protein,
            rn.carbs,
            rn.fat,
            rn.fiber

        FROM subscription_plan_products spp

        INNER JOIN products p
            ON p.id = spp.product_id

        LEFT JOIN recipes r
            ON r.product_id = p.id

        LEFT JOIN recipe_nutrition rn
            ON rn.recipe_id = r.id

        WHERE spp.plan_id = ?
        AND p.status = 'ACTIVE'

        ORDER BY spp.delivery_order ASC
    ");

    $productStmt->execute([$planId]);

    $rows = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];

    foreach ($rows as $row) {

        $products[] = [
            "id" => (int)$row["id"],
            "name" => $row["name"],
            "quantity" => (float)$row["quantity"],
            "unit" => $row["unit"],
            "image_url" => $row["image_url"],
            "is_subscription_only" => (bool)($row["is_subscription_only"] ?? 0),
            "delivery_order" => (int)$row["delivery_order"],

            "nutrition" => [
                "calories" => (float)($row["calories"] ?? 0),
                "protein" => (float)($row["protein"] ?? 0),
                "carbs" => (float)($row["carbs"] ?? 0),
                "fat" => (float)($row["fat"] ?? 0),
                "fiber" => (float)($row["fiber"] ?? 0)
            ]
        ];
    }

    echo json_encode([
        "success" => true,
        "plan" => $plan,
        "products" => $products
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}
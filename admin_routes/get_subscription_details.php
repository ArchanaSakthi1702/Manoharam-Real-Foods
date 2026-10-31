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

    $subscription_id = $_GET["id"] ?? null;

    if (!$subscription_id) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Subscription ID is required"
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
    s.id,
    s.start_date,
    s.end_date,
    s.status,
    s.created_at,
    s.approved_at,
    s.weeks,
    s.total_amount,
    s.exclude_saturday,
    s.exclude_sunday,

    u.id AS customer_id,
    u.name AS customer_name,
    u.email,
    u.phone,
    u.address,

    sp.id AS plan_id,
    sp.name AS plan_name,
    sp.category AS plan_category,
    sp.price_per_week,
    sp.description AS plan_description

FROM subscriptions s

INNER JOIN users u
    ON u.id = s.customer_id

INNER JOIN subscription_plans sp
    ON sp.id = s.plan_id

WHERE s.id = ?
LIMIT 1
    ");

    $stmt->execute([$subscription_id]);

    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Subscription not found"
        ]);

        exit;
    }

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

$productStmt->execute([
    $subscription["plan_id"]
]);

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all products linked to the subscription plan

    $skipStmt = $pdo->prepare("
    SELECT
        skip_date,
        refund_amount,
        reason,
        skip_type
    FROM subscription_skips
    WHERE subscription_id = ?
    ORDER BY skip_date ASC
");

$skipStmt->execute([
    $subscription["id"]
]);

$skipDates = array_map(
    function ($skip) {
        return [
            "skip_date" => $skip["skip_date"],
            "refund_amount" => (float)$skip["refund_amount"],
            "reason" => $skip["reason"],
            "skip_type" => $skip["skip_type"]
        ];
    },
    $skipStmt->fetchAll(PDO::FETCH_ASSOC)
);

$deliverySchedule = [];

if (!empty($products)) {

    $skipLookup = [];

    foreach ($skipDates as $skip) {
        $skipLookup[$skip["skip_date"]] = true;
    }

    $currentDate = new DateTime(
        $subscription["start_date"]
    );

    $endDate = new DateTime(
        $subscription["end_date"]
    );

    $productCount = count($products);
    $productIndex = 0;

    $requiredDeliveries =
    $subscription["weeks"] * 7;

while ($productIndex < $requiredDeliveries) {

        $dateString =
            $currentDate->format("Y-m-d");

       if (isset($skipLookup[$dateString])) {
    $currentDate->modify("+1 day");
    continue;
}

        $product =
            $products[
                $productIndex % $productCount
            ];

        $deliverySchedule[] = [

            "sequence" =>
                $productIndex + 1,

            "delivery_date" =>
                $dateString,

            "product_id" =>
                (int)$product["id"],

            "product_name" =>
                $product["name"]
        ];

        $productIndex++;

        $currentDate->modify("+1 day");
    }
}

    echo json_encode([
    "success" => true,

    "subscription" => [

        "id" =>
            (int)$subscription["id"],

        "status" =>
            $subscription["status"],

        "weeks" =>
            (int)$subscription["weeks"],

        "total_amount" =>
            (float)$subscription["total_amount"],

        "start_date" =>
            $subscription["start_date"],

        "end_date" =>
            $subscription["end_date"],

        "exclude_saturday" =>
            (bool)$subscription["exclude_saturday"],

        "exclude_sunday" =>
            (bool)$subscription["exclude_sunday"],

        "created_at" =>
            $subscription["created_at"],

        "approved_at" =>
            $subscription["approved_at"],

        "customer" => [

            "id" =>
                (int)$subscription["customer_id"],

            "name" =>
                $subscription["customer_name"],

            "email" =>
                $subscription["email"],

            "phone" =>
                $subscription["phone"],

            "address" =>
                $subscription["address"]
        ],

        "plan" => [
            "id" => (int)$subscription["plan_id"],
            "name" => $subscription["plan_name"],
            "category" => $subscription["plan_category"],
            "price_per_week" => (float)$subscription["price_per_week"],
            "description" => $subscription["plan_description"]
        ],

        "products" => array_map(
            function ($product) {

                return [

                    "id" =>
                        (int)$product["id"],

                    "name" =>
                        $product["name"],

                    "quantity" =>
                        (float)$product["quantity"],

                    "unit" =>
                        $product["unit"],

                    "image_url" =>
                        $product["image_url"],

                    "is_subscription_only" =>
                        (bool)($product["is_subscription_only"] ?? 0),

                    "delivery_order" =>
                        (int)$product["delivery_order"],

                    "nutrition" => [

                        "calories" =>
                            (float)($product["calories"] ?? 0),

                        "protein" =>
                            (float)($product["protein"] ?? 0),

                        "carbs" =>
                            (float)($product["carbs"] ?? 0),

                        "fat" =>
                            (float)($product["fat"] ?? 0),

                        "fiber" =>
                            (float)($product["fiber"] ?? 0)
                    ]
                ];
            },
            $products
        ),

        "skip_dates" => $skipDates,

        "delivery_schedule" =>
            $deliverySchedule
    ]
]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
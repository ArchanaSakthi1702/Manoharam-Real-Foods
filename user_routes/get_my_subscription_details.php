
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

    $subscriptionId = (int)($_GET["subscription_id"] ?? 0);

    if ($subscriptionId <= 0) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid subscription_id"
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.plan_id,
            s.start_date,
            s.end_date,
            s.status,
            s.weeks,
            s.total_amount,
            s.exclude_saturday,
            s.exclude_sunday,

            sp.name,
            sp.category,
            sp.description,
            sp.price_per_week

        FROM subscriptions s

        INNER JOIN subscription_plans sp
            ON sp.id = s.plan_id

        WHERE s.id = ?
        AND s.customer_id = ?
    ");

    $stmt->execute([
        $subscriptionId,
        $user->id
    ]);

    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Subscription not found"
        ]);
        exit;
    }

    $subscription["id"] = (int)$subscription["id"];
    $subscription["plan_id"] = (int)$subscription["plan_id"];
    $subscription["weeks"] = (int)$subscription["weeks"];
    $subscription["price_per_week"] = (float)$subscription["price_per_week"];
    $subscription["total_amount"] = (float)$subscription["total_amount"];
    $subscription["exclude_saturday"] =
        (bool)$subscription["exclude_saturday"];
    $subscription["exclude_sunday"] =
        (bool)$subscription["exclude_sunday"];

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

    $rows = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    $deliverySchedule = [];

    foreach ($rows as $row) {

        $products[] = [
            "id" => (int)$row["id"],
            "name" => $row["name"],
            "quantity" => (float)$row["quantity"],
            "unit" => $row["unit"],
            "image_url" => $row["image_url"],
            "is_subscription_only" => (bool)($row["is_subscription_only"] ?? 0),

            "nutrition" => [
                "calories" => (float)($row["calories"] ?? 0),
                "protein" => (float)($row["protein"] ?? 0),
                "carbs" => (float)($row["carbs"] ?? 0),
                "fat" => (float)($row["fat"] ?? 0),
                "fiber" => (float)($row["fiber"] ?? 0)
            ]
        ];
    }

    if (!empty($rows)) {

        $currentDate = new DateTime(
            $subscription["start_date"]
        );

        $endDate = new DateTime(
            $subscription["end_date"]
        );

        $productCount = count($rows);
        $productIndex = 0;

        while ($currentDate <= $endDate) {

            $weekday = (int)$currentDate->format("w");
            // Sunday = 0, Saturday = 6

            if (
                ($weekday === 6 &&
                 $subscription["exclude_saturday"])
                ||
                ($weekday === 0 &&
                 $subscription["exclude_sunday"])
            ) {
                $currentDate->modify("+1 day");
                continue;
            }

            $product =
                $rows[
                    $productIndex % $productCount
                ];

            $deliverySchedule[] = [
                "delivery_date" =>
                    $currentDate->format("Y-m-d"),

                "product_id" =>
                    (int)$product["id"],

                "sequence" =>
                    $productIndex + 1
            ];

            $productIndex++;

            $currentDate->modify("+1 day");
        }
    }

    echo json_encode([
        "success" => true,

        "subscription" => $subscription,

        "products" => $products,

        "delivery_schedule" => $deliverySchedule
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}
?>

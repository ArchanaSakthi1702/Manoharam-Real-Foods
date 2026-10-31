<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/cors.php";
require_once "db.php";
require_once "helpers/date_helper.php"; // contains calculateEndDate()

try {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $planId = (int)($data["plan_id"] ?? 0);
    $weeks = (int)($data["weeks"] ?? 0);
    $startDate = trim($data["start_date"] ?? "");

    $excludeSaturday =
        (bool)($data["exclude_saturday"] ?? false);

    $excludeSunday =
        (bool)($data["exclude_sunday"] ?? false);

    if (
        $planId <= 0 ||
        $weeks <= 0 ||
        empty($startDate)
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "plan_id, weeks and start_date are required"
        ]);
        exit;
    }

    $date = DateTime::createFromFormat(
        "Y-m-d",
        $startDate
    );

    if (
        !$date ||
        $date->format("Y-m-d") !== $startDate
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid start_date format"
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

    $endDate = calculateEndDate(
        $startDate,
        $weeks,
        $excludeSaturday,
        $excludeSunday
    );

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

    // Fetch administrative service holidays falling in this period
    $stmtHolidays = $pdo->prepare("
        SELECT holiday_date, reason FROM service_holidays
        WHERE holiday_date >= ? AND holiday_date <= ?
        ORDER BY holiday_date ASC
    ");
    $stmtHolidays->execute([$startDate, $endDate]);
    $serviceHolidays = $stmtHolidays->fetchAll(PDO::FETCH_ASSOC);
    $holidayMap = array_column($serviceHolidays, "reason", "holiday_date");

    $deliverySchedule = [];

    if (!empty($rows)) {

        $currentDate = new DateTime($startDate);
        $lastDate = new DateTime($endDate);

        $productCount = count($rows);
        $productIndex = 0;

        while ($currentDate <= $lastDate) {

            $dateStr = $currentDate->format("Y-m-d");
            $weekday = (int)$currentDate->format("w");

            if (
                ($weekday === 6 && $excludeSaturday)
                ||
                ($weekday === 0 && $excludeSunday)
            ) {
                $currentDate->modify("+1 day");
                continue;
            }

            // If this date is an admin declared service holiday, skip it in delivery schedule
            if (isset($holidayMap[$dateStr])) {
                $currentDate->modify("+1 day");
                continue;
            }

            $product =
                $rows[
                    $productIndex % $productCount
                ];

            $deliverySchedule[] = [

                "sequence" =>
                    $productIndex + 1,

                "delivery_date" =>
                    $currentDate->format("Y-m-d"),

                "product" => [

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
                ]
            ];

            $productIndex++;

            $currentDate->modify("+1 day");
        }
    }

    echo json_encode([
        "success" => true,

        "plan" => [
            "id" => (int)$plan["id"],
            "name" => $plan["name"],
            "category" => $plan["category"],
            "description" => $plan["description"],
            "price_per_week" =>
                (float)$plan["price_per_week"]
        ],

        "weeks" => $weeks,

        "start_date" => $startDate,

        "end_date" => $endDate,

        "exclude_saturday" =>
            $excludeSaturday,

        "exclude_sunday" =>
            $excludeSunday,

        "service_holidays" =>
            $serviceHolidays,

        "total_deliveries" =>
            count($deliverySchedule),

        "delivery_schedule" =>
            $deliverySchedule
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}
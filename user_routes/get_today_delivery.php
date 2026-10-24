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

$targetDate = trim($_GET["date"] ?? date("Y-m-d"));

$d = DateTime::createFromFormat("Y-m-d", $targetDate);
if (!$d || $d->format("Y-m-d") !== $targetDate) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid date format. Expected YYYY-MM-DD"
    ]);
    exit;
}

$weekday = (int)$d->format("w"); // 0 = Sunday, 6 = Saturday

try {
    // 1. Fetch customer's active/approved subscriptions covering target date
    $stmt = $pdo->prepare("
        SELECT
            s.id AS subscription_id,
            s.plan_id,
            s.start_date,
            s.end_date,
            s.status AS subscription_status,
            s.exclude_saturday,
            s.exclude_sunday,
            sp.name AS plan_name,
            sp.category AS plan_category,
            sp.description AS plan_description,
            sp.price_per_week,
            ROUND(sp.price_per_week / 7, 2) AS daily_rate,
            COALESCE(
                (SELECT CONCAT(address_line1, ', ', city, ' - ', postal_code) FROM user_addresses WHERE user_id = u.id AND is_default = 1 LIMIT 1),
                u.address
            ) AS delivery_address
        FROM subscriptions s
        INNER JOIN subscription_plans sp ON sp.id = s.plan_id
        INNER JOIN users u ON u.id = s.customer_id
        WHERE s.customer_id = ?
          AND s.status IN ('ACTIVE', 'APPROVED')
          AND s.start_date <= ?
          AND s.end_date >= ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$user->id, $targetDate, $targetDate]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subscriptionDeliveries = [];
    $hasDeliveryToday = false;

    foreach ($subscriptions as $sub) {
        $subId = (int)$sub["subscription_id"];
        $planId = (int)$sub["plan_id"];

        // A. Check weekend exclusions
        if ($weekday === 6 && (bool)$sub["exclude_saturday"]) {
            $subscriptionDeliveries[] = [
                "subscription_id"   => $subId,
                "plan_name"         => $sub["plan_name"],
                "delivery_status"   => "WEEKEND_EXCLUDED",
                "is_delivering"     => false,
                "message"           => "No delivery today. Saturday is excluded from your weekly subscription.",
                "scheduled_product" => null,
                "delivery_address"  => $sub["delivery_address"]
            ];
            continue;
        }

        if ($weekday === 0 && (bool)$sub["exclude_sunday"]) {
            $subscriptionDeliveries[] = [
                "subscription_id"   => $subId,
                "plan_name"         => $sub["plan_name"],
                "delivery_status"   => "WEEKEND_EXCLUDED",
                "is_delivering"     => false,
                "message"           => "No delivery today. Sunday is excluded from your weekly subscription.",
                "scheduled_product" => null,
                "delivery_address"  => $sub["delivery_address"]
            ];
            continue;
        }

        // B. Check if date is skipped (Customer skip or Admin holiday)
        $stmtSkip = $pdo->prepare("
            SELECT skip_date, refund_amount, reason, skip_type
            FROM subscription_skips
            WHERE subscription_id = ? AND skip_date = ?
            LIMIT 1
        ");
        $stmtSkip->execute([$subId, $targetDate]);
        $skip = $stmtSkip->fetch(PDO::FETCH_ASSOC);

        if ($skip) {
            $isHoliday = ($skip["skip_type"] === "HOLIDAY");
            $subscriptionDeliveries[] = [
                "subscription_id"   => $subId,
                "plan_name"         => $sub["plan_name"],
                "delivery_status"   => $isHoliday ? "SERVICE_HOLIDAY" : "CUSTOMER_SKIPPED",
                "is_delivering"     => false,
                "message"           => $isHoliday
                    ? "Kitchen/Service holiday today: " . ($skip["reason"] ?: "Closed") . ". Daily refund of ₹" . number_format((float)$skip["refund_amount"], 2) . " applied."
                    : "You skipped today's delivery: " . ($skip["reason"] ?: "Custom skip") . ". Daily refund of ₹" . number_format((float)$skip["refund_amount"], 2) . " credited.",
                "skip_details"      => [
                    "skip_type"     => $skip["skip_type"],
                    "reason"        => $skip["reason"],
                    "refund_amount" => (float)$skip["refund_amount"]
                ],
                "scheduled_product" => null,
                "delivery_address"  => $sub["delivery_address"]
            ];
            continue;
        }

        // C. Calculate scheduled product for today using plan rotation
        $stmtProducts = $pdo->prepare("
            SELECT
                p.id,
                p.name,
                p.category,
                p.quantity,
                p.unit,
                p.selling_price,
                p.description,
                p.image_url,
                spp.delivery_order,
                rn.calories,
                rn.protein,
                rn.carbs,
                rn.fat,
                rn.fiber
            FROM subscription_plan_products spp
            INNER JOIN products p ON p.id = spp.product_id
            LEFT JOIN recipes r ON r.product_id = p.id
            LEFT JOIN recipe_nutrition rn ON rn.recipe_id = r.id
            WHERE spp.plan_id = ? AND p.status = 'ACTIVE'
            ORDER BY spp.delivery_order ASC
        ");
        $stmtProducts->execute([$planId]);
        $planProducts = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

        $scheduledProduct = null;

        if (!empty($planProducts)) {
            // Count active delivery days from start_date to targetDate
            $curr = new DateTime($sub["start_date"]);
            $end = new DateTime($targetDate);
            $deliveryDayIndex = 0;

            while ($curr <= $end) {
                $w = (int)$curr->format("w");
                $isWeekendExcl = ($w === 6 && $sub["exclude_saturday"]) || ($w === 0 && $sub["exclude_sunday"]);

                if (!$isWeekendExcl) {
                    $deliveryDayIndex++;
                }
                $curr->modify("+1 day");
            }

            $prodCount = count($planProducts);
            $productKey = ($deliveryDayIndex > 0 ? $deliveryDayIndex - 1 : 0) % $prodCount;
            $p = $planProducts[$productKey];

            $scheduledProduct = [
                "product_id"    => (int)$p["id"],
                "name"          => $p["name"],
                "category"      => $p["category"],
                "portion_size"  => (float)$p["quantity"],
                "unit"          => $p["unit"],
                "selling_price" => (float)$p["selling_price"],
                "description"   => $p["description"],
                "image_url"     => $p["image_url"],
                "nutrition"     => [
                    "calories" => (float)($p["calories"] ?? 0),
                    "protein"  => (float)($p["protein"] ?? 0),
                    "carbs"    => (float)($p["carbs"] ?? 0),
                    "fat"      => (float)($p["fat"] ?? 0),
                    "fiber"    => (float)($p["fiber"] ?? 0)
                ]
            ];
        }

        $hasDeliveryToday = true;

        $subscriptionDeliveries[] = [
            "subscription_id"   => $subId,
            "plan_name"         => $sub["plan_name"],
            "delivery_status"   => "DELIVERING_TODAY",
            "is_delivering"     => true,
            "message"           => "Your scheduled product for today is " . ($scheduledProduct ? $scheduledProduct["name"] : "Ready for dispatch"),
            "scheduled_product" => $scheduledProduct,
            "delivery_address"  => $sub["delivery_address"]
        ];
    }

    // 2. Also check if any direct e-commerce orders are out for delivery / delivering today
    $stmtOrders = $pdo->prepare("
        SELECT
            o.id AS order_id,
            o.order_date,
            o.status,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.delivery_address
        FROM orders o
        WHERE o.customer_id = ?
          AND (
              o.status = 'OUT_FOR_DELIVERY'
              OR (DATE(o.order_date) = ? AND o.status != 'CANCELLED')
          )
        ORDER BY o.id DESC
    ");
    $stmtOrders->execute([$user->id, $targetDate]);
    $directOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

    foreach ($directOrders as &$order) {
        $stmtItems = $pdo->prepare("
            SELECT
                oi.product_id,
                p.name,
                oi.quantity,
                oi.price,
                p.unit,
                p.image_url
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$order["order_id"]]);
        $order["items"] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if ($order["status"] === "OUT_FOR_DELIVERY") {
            $hasDeliveryToday = true;
        }
    }

    echo json_encode([
        "success" => true,
        "date" => $targetDate,
        "day_of_week" => $d->format("l"),
        "has_delivery_today" => $hasDeliveryToday,
        "primary_delivery" => $subscriptionDeliveries[0] ?? null,
        "subscription_deliveries" => $subscriptionDeliveries,
        "direct_orders" => $directOrders
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

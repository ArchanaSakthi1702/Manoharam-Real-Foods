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
    // 1. Fetch active subscriptions covering this date
    $sql = "
        SELECT
            s.id AS subscription_id,
            s.customer_id,
            s.plan_id,
            s.start_date,
            s.end_date,
            s.exclude_saturday,
            s.exclude_sunday,
            u.name AS customer_name,
            u.phone AS customer_phone,
            u.address AS customer_address,
            sp.name AS plan_name,
            sp.category AS plan_category
        FROM subscriptions s
        INNER JOIN users u ON u.id = s.customer_id
        INNER JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.status IN ('ACTIVE', 'APPROVED')
          AND s.start_date <= :date
          AND s.end_date >= :date
          AND NOT EXISTS (
              SELECT 1 FROM subscription_skips ss
              WHERE ss.subscription_id = s.id
                AND ss.skip_date = :date
          )
    ";

    if ($weekday === 6) {
        $sql .= " AND s.exclude_saturday = 0 ";
    } elseif ($weekday === 0) {
        $sql .= " AND s.exclude_sunday = 0 ";
    }

    $sql .= " ORDER BY u.name ASC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([":date" => $targetDate]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch products for each plan
    $planIds = array_unique(array_column($subscriptions, "plan_id"));
    $productsByPlan = [];

    if (!empty($planIds)) {
        $placeholders = implode(",", array_fill(0, count($planIds), "?"));
        $prodStmt = $pdo->prepare("
            SELECT
                spp.plan_id,
                p.id AS product_id,
                p.name AS product_name,
                p.quantity,
                p.unit,
                p.selling_price,
                spp.delivery_order
            FROM subscription_plan_products spp
            INNER JOIN products p ON p.id = spp.product_id
            WHERE spp.plan_id IN ($placeholders)
              AND p.status = 'ACTIVE'
            ORDER BY spp.plan_id ASC, spp.delivery_order ASC
        ");
        $prodStmt->execute(array_values($planIds));
        $planProds = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($planProds as $pp) {
            $productsByPlan[$pp["plan_id"]][] = $pp;
        }
    }

    // 3. Determine scheduled product for each delivery & build kitchen summary
    $deliveries = [];
    $kitchenSummary = [];

    foreach ($subscriptions as $sub) {
        $planProducts = $productsByPlan[$sub["plan_id"]] ?? [];
        $scheduledProduct = null;

        if (!empty($planProducts)) {
            // Count delivery days elapsed from start_date to targetDate
            $curr = new DateTime($sub["start_date"]);
            $end = new DateTime($targetDate);
            $deliveryDayIndex = 0;

            while ($curr <= $end) {
                $w = (int)$curr->format("w");
                $isWeekendExcluded = ($w === 6 && $sub["exclude_saturday"]) || ($w === 0 && $sub["exclude_sunday"]);

                if (!$isWeekendExcluded) {
                    $deliveryDayIndex++;
                }
                $curr->modify("+1 day");
            }

            $prodCount = count($planProducts);
            $productKey = ($deliveryDayIndex > 0 ? $deliveryDayIndex - 1 : 0) % $prodCount;
            $scheduledProduct = $planProducts[$productKey];
        }

        $deliveries[] = [
            "subscription_id"   => (int)$sub["subscription_id"],
            "customer_id"       => (int)$sub["customer_id"],
            "customer_name"     => $sub["customer_name"],
            "customer_phone"    => $sub["customer_phone"],
            "delivery_address"  => $sub["customer_address"],
            "plan_name"         => $sub["plan_name"],
            "scheduled_product" => $scheduledProduct ? [
                "product_id" => (int)$scheduledProduct["product_id"],
                "name"       => $scheduledProduct["product_name"],
                "quantity"   => (float)$scheduledProduct["quantity"],
                "unit"       => $scheduledProduct["unit"]
            ] : null
        ];

        // Aggregate kitchen production requirement
        if ($scheduledProduct) {
            $pId = $scheduledProduct["product_id"];
            if (!isset($kitchenSummary[$pId])) {
                $kitchenSummary[$pId] = [
                    "product_id"   => (int)$pId,
                    "product_name" => $scheduledProduct["product_name"],
                    "unit"         => $scheduledProduct["unit"],
                    "portion_size" => (float)$scheduledProduct["quantity"],
                    "total_packs"  => 0
                ];
            }
            $kitchenSummary[$pId]["total_packs"]++;
        }
    }

    echo json_encode([
        "success" => true,
        "dispatch_date" => $targetDate,
        "day_of_week" => $d->format("l"),
        "total_deliveries" => count($deliveries),
        "kitchen_production_summary" => array_values($kitchenSummary),
        "deliveries" => $deliveries
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

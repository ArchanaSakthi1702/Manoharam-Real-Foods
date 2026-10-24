
<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

require_once "../helpers/date_helper.php";
require_once "../helpers/pricing_helper.php";
require_once "../helpers/skip_helper.php";
require_once "../helpers/subscription_helper.php";

$user = authenticate();

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$plan_id = (int)($data["plan_id"] ?? 0);
$weeks = (int)($data["weeks"] ?? 0);
$start_date = trim($data["start_date"] ?? "");

$excludeSaturday =
    (bool)($data["exclude_saturday"] ?? false);

$excludeSunday =
    (bool)($data["exclude_sunday"] ?? false);

$skipDates =
    $data["skip_dates"] ?? [];

if (!is_array($skipDates)) {
    $skipDates = [];
}

try {

    if (
        $plan_id <= 0 ||
        $weeks <= 0 ||
        empty($start_date)
    ) {
        throw new Exception(
            "plan_id, weeks and start_date are required"
        );
    }

    $date = DateTime::createFromFormat(
        "Y-m-d",
        $start_date
    );

    if (
        !$date ||
        $date->format("Y-m-d") !== $start_date
    ) {
        throw new Exception(
            "Invalid start_date format. Use YYYY-MM-DD"
        );
    }

    if ($start_date < date("Y-m-d")) {
        throw new Exception(
            "start_date cannot be in the past"
        );
    }

    $pdo->beginTransaction();

    $plan = getSubscriptionPlan(
        $pdo,
        $plan_id
    );

    $stmt = $pdo->prepare("
        SELECT id
        FROM subscriptions
        WHERE customer_id = ?
        AND plan_id = ?
        AND status IN (
            'PENDING',
            'ACTIVE'
        )
        LIMIT 1
    ");

    $stmt->execute([
        $user->id,
        $plan_id
    ]);

    if ($stmt->fetch()) {
        throw new Exception(
            "You already have an active subscription for this plan."
        );
    }

    $end_date = calculateEndDate(
        $start_date,
        $weeks,
        $excludeSaturday,
        $excludeSunday
    );

    validateSkipDates(
        $skipDates,
        $start_date,
        $end_date,
        $excludeSaturday,
        $excludeSunday
    );

    $pricing =
        calculateSubscriptionPricing(
            (float)$plan["price_per_week"],
            $weeks,
            count($skipDates)
        );

    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (
            customer_id,
            plan_id,
            weeks,
            total_amount,
            start_date,
            end_date,
            exclude_saturday,
            exclude_sunday,
            status
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING'
        )
    ");

    $stmt->execute([
        $user->id,
        $plan_id,
        $weeks,
        $pricing["total_amount"],
        $start_date,
        $end_date,
        $excludeSaturday ? 1 : 0,
        $excludeSunday ? 1 : 0
    ]);

    $subscription_id =
        (int)$pdo->lastInsertId();

    insertWeekendSkips(
        $pdo,
        $subscription_id,
        $start_date,
        $end_date,
        $excludeSaturday,
        $excludeSunday
    );

    insertCustomSkips(
        $pdo,
        $subscription_id,
        $skipDates,
        $pricing["per_day_amount"]
    );

    // Automatically record administrative service holiday skips with refund
    $stmtHolidays = $pdo->prepare("
        SELECT holiday_date, reason FROM service_holidays
        WHERE holiday_date >= ? AND holiday_date <= ?
    ");
    $stmtHolidays->execute([$start_date, $end_date]);
    $holidays = $stmtHolidays->fetchAll(PDO::FETCH_ASSOC);

    $stmtHolidaySkip = $pdo->prepare("
        INSERT INTO subscription_skips (
            subscription_id, skip_date, refund_amount, reason, skip_type
        ) VALUES (?, ?, ?, ?, 'HOLIDAY')
        ON DUPLICATE KEY UPDATE reason = VALUES(reason), skip_type = 'HOLIDAY'
    ");

    foreach ($holidays as $h) {
        $stmtHolidaySkip->execute([
            $subscription_id,
            $h["holiday_date"],
            $pricing["per_day_amount"],
            $h["reason"]
        ]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" =>
            "Subscription created successfully",

        "subscription" => [
            "id" => $subscription_id,

            "plan_id" =>
                (int)$plan["id"],

            "plan_name" =>
                $plan["name"],

            "weeks" => $weeks,

            "price_per_week" =>
                (float)$plan["price_per_week"],

            "base_amount" =>
                $pricing["base_amount"],

            "discount_amount" =>
                $pricing["discount_amount"],

            "custom_skip_count" =>
                count($skipDates),

            "total_amount" =>
                $pricing["total_amount"],

            "start_date" =>
                $start_date,

            "end_date" =>
                $end_date,

            "status" =>
                "PENDING",

            "exclude_saturday" =>
                $excludeSaturday,

            "exclude_sunday" =>
                $excludeSunday,

            "skip_dates" =>
                $skipDates
        ]
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Something went wrong"
    ]);
}
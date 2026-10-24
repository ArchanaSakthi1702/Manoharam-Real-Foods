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
require_once "../auth/admin_middleware.php";

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

$reason = trim($data["reason"] ?? "Service not available / Kitchen holiday");
$dates = [];

if (!empty($data["date"])) {
    $dates[] = trim($data["date"]);
} elseif (!empty($data["dates"]) && is_array($data["dates"])) {
    $dates = array_map('trim', $data["dates"]);
}

if (empty($dates)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "date (or an array of dates) is required"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $today = date("Y-m-d");
    $addedDates = [];
    $totalAffectedSubscriptions = 0;
    $totalRefundAmount = 0.0;

    $stmtInsertHoliday = $pdo->prepare("
        INSERT INTO service_holidays (holiday_date, reason, created_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_by = VALUES(created_by)
    ");

    $stmtFindSubs = $pdo->prepare("
        SELECT
            s.id AS subscription_id,
            s.customer_id,
            s.start_date,
            s.end_date,
            s.exclude_saturday,
            s.exclude_sunday,
            sp.price_per_week,
            ROUND(sp.price_per_week / 7, 2) AS daily_rate
        FROM subscriptions s
        INNER JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.status IN ('ACTIVE', 'APPROVED')
          AND s.start_date <= ?
          AND s.end_date >= ?
    ");

    $stmtInsertSkip = $pdo->prepare("
        INSERT INTO subscription_skips (
            subscription_id,
            skip_date,
            refund_amount,
            reason,
            skip_type
        )
        VALUES (?, ?, ?, ?, 'HOLIDAY')
        ON DUPLICATE KEY UPDATE
            refund_amount = VALUES(refund_amount),
            reason = VALUES(reason),
            skip_type = 'HOLIDAY'
    ");

    foreach ($dates as $dateStr) {
        $d = DateTime::createFromFormat("Y-m-d", $dateStr);
        if (!$d || $d->format("Y-m-d") !== $dateStr) {
            throw new Exception("Invalid date format for '{$dateStr}'. Expected YYYY-MM-DD");
        }

        if ($dateStr < $today) {
            throw new Exception("Cannot declare past dates as service holidays ({$dateStr})");
        }

        // 1. Record holiday
        $stmtInsertHoliday->execute([$dateStr, $reason, $admin->id]);
        $addedDates[] = $dateStr;

        // 2. Find active subscriptions running during this holiday
        $stmtFindSubs->execute([$dateStr, $dateStr]);
        $activeSubs = $stmtFindSubs->fetchAll(PDO::FETCH_ASSOC);

        $dayOfWeek = (int)$d->format("w"); // 0 = Sunday, 6 = Saturday

        foreach ($activeSubs as $sub) {
            // If already excluded by regular weekend exclusion, no additional skip/refund needed
            if ($dayOfWeek === 6 && (bool)$sub["exclude_saturday"]) {
                continue;
            }
            if ($dayOfWeek === 0 && (bool)$sub["exclude_sunday"]) {
                continue;
            }

            $refund = (float)$sub["daily_rate"];

            $stmtInsertSkip->execute([
                $sub["subscription_id"],
                $dateStr,
                $refund,
                $reason
            ]);

            $totalAffectedSubscriptions++;
            $totalRefundAmount += $refund;
        }
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Service unavailable day(s) registered successfully. Affected subscriptions have been automatically skipped with refunds.",
        "dates_added" => $addedDates,
        "reason" => $reason,
        "total_subscriptions_skipped" => $totalAffectedSubscriptions,
        "total_refund_amount" => round($totalRefundAmount, 2)
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
}

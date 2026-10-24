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

$month = trim($_GET["month"] ?? ""); // e.g. '2026-11'
$year  = trim($_GET["year"] ?? "");  // e.g. '2026'

try {
    $sql = "
        SELECT
            h.id,
            h.holiday_date,
            h.reason,
            h.created_at,
            u.name AS created_by_admin,
            COUNT(ss.id) AS total_subscriptions_skipped,
            COALESCE(SUM(ss.refund_amount), 0) AS total_refund_amount
        FROM service_holidays h
        LEFT JOIN users u ON u.id = h.created_by
        LEFT JOIN subscription_skips ss ON ss.skip_date = h.holiday_date AND ss.skip_type = 'HOLIDAY'
        WHERE 1=1
    ";

    $params = [];

    if (!empty($month)) {
        $sql .= " AND DATE_FORMAT(h.holiday_date, '%Y-%m') = ? ";
        $params[] = $month;
    } elseif (!empty($year)) {
        $sql .= " AND DATE_FORMAT(h.holiday_date, '%Y') = ? ";
        $params[] = $year;
    }

    $sql .= "
        GROUP BY h.id
        ORDER BY h.holiday_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($holidays as &$h) {
        $h["id"] = (int)$h["id"];
        $h["total_subscriptions_skipped"] = (int)$h["total_subscriptions_skipped"];
        $h["total_refund_amount"] = (float)$h["total_refund_amount"];
    }

    echo json_encode([
        "success" => true,
        "total_holidays" => count($holidays),
        "holidays" => $holidays
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/cors.php";
require_once "db.php";

$month = trim($_GET["month"] ?? ""); // e.g. '2026-11'
$year  = trim($_GET["year"] ?? "");

try {
    $sql = "
        SELECT
            id,
            holiday_date,
            reason
        FROM service_holidays
        WHERE holiday_date >= CURDATE()
    ";
    $params = [];

    if (!empty($month)) {
        $sql .= " AND DATE_FORMAT(holiday_date, '%Y-%m') = ? ";
        $params[] = $month;
    } elseif (!empty($year)) {
        $sql .= " AND DATE_FORMAT(holiday_date, '%Y') = ? ";
        $params[] = $year;
    }

    $sql .= " ORDER BY holiday_date ASC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

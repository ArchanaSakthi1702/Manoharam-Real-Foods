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

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

$holidayId   = $data["id"] ?? $data["holiday_id"] ?? null;
$holidayDate = trim($data["holiday_date"] ?? $data["date"] ?? "");

if (!$holidayId && empty($holidayDate)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "id or holiday_date is required"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($holidayId) {
        $stmt = $pdo->prepare("SELECT holiday_date FROM service_holidays WHERE id = ? LIMIT 1");
        $stmt->execute([$holidayId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("Service holiday record not found");
        }
        $holidayDate = $row["holiday_date"];
    }

    // 1. Delete holiday from service_holidays
    $stmt = $pdo->prepare("DELETE FROM service_holidays WHERE holiday_date = ?");
    $stmt->execute([$holidayDate]);

    // 2. Remove future auto-created holiday skips from subscription_skips
    $today = date("Y-m-d");
    $stmtSkips = $pdo->prepare("
        DELETE FROM subscription_skips
        WHERE skip_date = ? AND skip_type = 'HOLIDAY' AND skip_date >= ?
    ");
    $stmtSkips->execute([$holidayDate, $today]);
    $resumedCount = $stmtSkips->rowCount();

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Service holiday removed successfully. Subscriptions have resumed for this date.",
        "holiday_date" => $holidayDate,
        "subscriptions_resumed" => $resumedCount
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

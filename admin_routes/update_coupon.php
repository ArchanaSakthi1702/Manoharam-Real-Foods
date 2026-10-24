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
$couponId = $data["id"] ?? $data["coupon_id"] ?? null;

if (!$couponId || !is_numeric($couponId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid coupon_id is required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? LIMIT 1");
    $stmt->execute([$couponId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Coupon not found"
        ]);
        exit;
    }

    $code              = isset($data["code"]) ? strtoupper(trim($data["code"])) : $existing["code"];
    $description       = isset($data["description"]) ? trim($data["description"]) : $existing["description"];
    $discountType      = isset($data["discount_type"]) ? strtoupper(trim($data["discount_type"])) : $existing["discount_type"];
    $discountValue     = isset($data["discount_value"]) ? (float)$data["discount_value"] : (float)$existing["discount_value"];
    $minOrderAmount    = isset($data["min_order_amount"]) ? (float)$data["min_order_amount"] : (float)$existing["min_order_amount"];
    $maxDiscountAmount = array_key_exists("max_discount_amount", $data) ? ($data["max_discount_amount"] !== null ? (float)$data["max_discount_amount"] : null) : $existing["max_discount_amount"];
    $startDate         = array_key_exists("start_date", $data) ? $data["start_date"] : $existing["start_date"];
    $endDate           = array_key_exists("end_date", $data) ? $data["end_date"] : $existing["end_date"];
    $usageLimit        = array_key_exists("usage_limit", $data) ? ($data["usage_limit"] !== null ? (int)$data["usage_limit"] : null) : $existing["usage_limit"];
    $status            = isset($data["status"]) ? strtoupper(trim($data["status"])) : $existing["status"];

    if (!in_array($discountType, ["PERCENTAGE", "FIXED"])) {
        throw new Exception("discount_type must be either PERCENTAGE or FIXED");
    }

    if ($discountType === "PERCENTAGE" && $discountValue > 100) {
        throw new Exception("Percentage discount cannot exceed 100%");
    }

    $stmt = $pdo->prepare("
        UPDATE coupons
        SET
            code = ?,
            description = ?,
            discount_type = ?,
            discount_value = ?,
            min_order_amount = ?,
            max_discount_amount = ?,
            start_date = ?,
            end_date = ?,
            usage_limit = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $code,
        $description,
        $discountType,
        $discountValue,
        $minOrderAmount,
        $maxDiscountAmount,
        $startDate,
        $endDate,
        $usageLimit,
        $status,
        $couponId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Coupon updated successfully",
        "coupon_id" => (int)$couponId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

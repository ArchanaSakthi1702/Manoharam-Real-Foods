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

$code              = strtoupper(trim($data["code"] ?? ""));
$description       = trim($data["description"] ?? "");
$discountType      = strtoupper(trim($data["discount_type"] ?? "PERCENTAGE"));
$discountValue     = isset($data["discount_value"]) ? (float)$data["discount_value"] : null;
$minOrderAmount    = isset($data["min_order_amount"]) ? (float)$data["min_order_amount"] : 0.00;
$maxDiscountAmount = isset($data["max_discount_amount"]) && $data["max_discount_amount"] !== null ? (float)$data["max_discount_amount"] : null;
$startDate         = !empty($data["start_date"]) ? trim($data["start_date"]) : null;
$endDate           = !empty($data["end_date"]) ? trim($data["end_date"]) : null;
$usageLimit        = isset($data["usage_limit"]) && $data["usage_limit"] !== null ? (int)$data["usage_limit"] : null;
$status            = strtoupper(trim($data["status"] ?? "ACTIVE"));

if (empty($code) || $discountValue === null || $discountValue <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "code and valid positive discount_value are required"
    ]);
    exit;
}

if (!in_array($discountType, ["PERCENTAGE", "FIXED"])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "discount_type must be either PERCENTAGE or FIXED"
    ]);
    exit;
}

if ($discountType === "PERCENTAGE" && $discountValue > 100) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Percentage discount cannot exceed 100%"
    ]);
    exit;
}

try {
    // Check code uniqueness
    $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Coupon code already exists"
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO coupons (
            code,
            description,
            discount_type,
            discount_value,
            min_order_amount,
            max_discount_amount,
            start_date,
            end_date,
            usage_limit,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $code,
        $description ?: null,
        $discountType,
        $discountValue,
        $minOrderAmount,
        $maxDiscountAmount,
        $startDate,
        $endDate,
        $usageLimit,
        $status
    ]);

    $couponId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Coupon created successfully",
        "coupon_id" => (int)$couponId,
        "code" => $code
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

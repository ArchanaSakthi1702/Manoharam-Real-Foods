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
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$couponId]);

    echo json_encode([
        "success" => true,
        "message" => "Coupon deleted successfully",
        "coupon_id" => (int)$couponId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

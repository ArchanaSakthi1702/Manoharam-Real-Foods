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

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);
$code = strtoupper(trim($data["code"] ?? ""));
$orderAmount = isset($data["order_amount"]) ? (float)$data["order_amount"] : null;

if (empty($code)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Coupon code is required"
    ]);
    exit;
}

try {
    // If order_amount not provided, calculate from user's current cart
    if ($orderAmount === null) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ci.quantity * p.selling_price), 0) AS cart_total
            FROM carts c
            INNER JOIN cart_items ci ON ci.cart_id = c.id
            INNER JOIN products p ON p.id = ci.product_id
            WHERE c.customer_id = ? AND p.status = 'ACTIVE'
        ");
        $stmt->execute([$user->id]);
        $orderAmount = (float)$stmt->fetchColumn();
    }

    if ($orderAmount <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Order amount must be greater than zero to apply coupon"
        ]);
        exit;
    }

    // Find coupon
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Invalid coupon code"
        ]);
        exit;
    }

    if ($coupon["status"] !== "ACTIVE") {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "This coupon is currently inactive"
        ]);
        exit;
    }

    $today = date("Y-m-d");
    if (!empty($coupon["start_date"]) && $today < $coupon["start_date"]) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Coupon is not yet active"
        ]);
        exit;
    }

    if (!empty($coupon["end_date"]) && $today > $coupon["end_date"]) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Coupon has expired"
        ]);
        exit;
    }

    if ($coupon["usage_limit"] !== null && (int)$coupon["used_count"] >= (int)$coupon["usage_limit"]) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Coupon usage limit has been reached"
        ]);
        exit;
    }

    $minOrderAmount = (float)$coupon["min_order_amount"];
    if ($orderAmount < $minOrderAmount) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Minimum order amount of ₹" . number_format($minOrderAmount, 2) . " required for this coupon"
        ]);
        exit;
    }

    // Calculate discount
    $discountValue = (float)$coupon["discount_value"];
    $discountAmount = 0;

    if ($coupon["discount_type"] === "PERCENTAGE") {
        $discountAmount = ($orderAmount * $discountValue) / 100;
        if ($coupon["max_discount_amount"] !== null) {
            $discountAmount = min($discountAmount, (float)$coupon["max_discount_amount"]);
        }
    } else {
        // FIXED
        $discountAmount = min($discountValue, $orderAmount);
    }

    $discountAmount = round($discountAmount, 2);
    $finalAmount = max(0, round($orderAmount - $discountAmount, 2));

    echo json_encode([
        "success" => true,
        "message" => "Coupon applied successfully!",
        "coupon" => [
            "code" => $coupon["code"],
            "description" => $coupon["description"],
            "discount_type" => $coupon["discount_type"],
            "discount_value" => $discountValue
        ],
        "order_amount" => $orderAmount,
        "discount_amount" => $discountAmount,
        "final_amount" => $finalAmount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

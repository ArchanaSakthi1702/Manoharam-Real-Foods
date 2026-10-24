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
require_once "../helpers/razorpay_helper.php";

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);

$orderId = isset($data["order_id"]) ? (int)$data["order_id"] : null;
$subscriptionId = isset($data["subscription_id"]) ? (int)$data["subscription_id"] : null;

if (!$orderId && !$subscriptionId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Either order_id or subscription_id is required"
    ]);
    exit;
}

try {
    if ($orderId) {
        $stmt = $pdo->prepare("
            SELECT id, customer_id, total_amount, status
            FROM orders
            WHERE id = ? AND customer_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId, $user->id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Order not found"
            ]);
            exit;
        }

        if ($order["status"] === "CANCELLED") {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Cannot create payment for a cancelled order"
            ]);
            exit;
        }

        $amount = (float)$order["total_amount"];
        $receipt = "rcpt_order_" . $order["id"];

        $razorpayOrder = RazorpayHelper::createOrder($amount, $receipt, [
            "order_id" => $order["id"],
            "customer_id" => $user->id,
            "type" => "DIRECT_ORDER"
        ]);

        // Attempt updating razorpay_order_id on orders table
        try {
            $updateStmt = $pdo->prepare("
                UPDATE orders
                SET razorpay_order_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$razorpayOrder["razorpay_order_id"], $orderId]);
        } catch (PDOException $pe) {
            // If column doesn't exist yet, continue safely
        }

        // Insert into payments table if exists
        try {
            $payStmt = $pdo->prepare("
                INSERT INTO payments (customer_id, order_id, razorpay_order_id, amount, currency, status)
                VALUES (?, ?, ?, ?, 'INR', 'CREATED')
            ");
            $payStmt->execute([$user->id, $orderId, $razorpayOrder["razorpay_order_id"], $amount]);
        } catch (PDOException $pe) {
            // Ignore if table not created yet
        }

        echo json_encode([
            "success" => true,
            "message" => "Razorpay order created successfully",
            "type" => "ORDER",
            "order_id" => $orderId,
            "razorpay" => $razorpayOrder
        ]);

    } else {
        // Subscription payment
        $stmt = $pdo->prepare("
            SELECT id, customer_id, total_amount, status
            FROM subscriptions
            WHERE id = ? AND customer_id = ?
            LIMIT 1
        ");
        $stmt->execute([$subscriptionId, $user->id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Subscription not found"
            ]);
            exit;
        }

        $amount = (float)$subscription["total_amount"];
        $receipt = "rcpt_sub_" . $subscription["id"];

        $razorpayOrder = RazorpayHelper::createOrder($amount, $receipt, [
            "subscription_id" => $subscription["id"],
            "customer_id" => $user->id,
            "type" => "SUBSCRIPTION"
        ]);

        try {
            $updateStmt = $pdo->prepare("
                UPDATE subscriptions
                SET razorpay_order_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$razorpayOrder["razorpay_order_id"], $subscriptionId]);
        } catch (PDOException $pe) {}

        try {
            $payStmt = $pdo->prepare("
                INSERT INTO payments (customer_id, subscription_id, razorpay_order_id, amount, currency, status)
                VALUES (?, ?, ?, ?, 'INR', 'CREATED')
            ");
            $payStmt->execute([$user->id, $subscriptionId, $razorpayOrder["razorpay_order_id"], $amount]);
        } catch (PDOException $pe) {}

        echo json_encode([
            "success" => true,
            "message" => "Razorpay order created successfully for subscription",
            "type" => "SUBSCRIPTION",
            "subscription_id" => $subscriptionId,
            "razorpay" => $razorpayOrder
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

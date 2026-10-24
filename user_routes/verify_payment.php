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

$orderId            = isset($data["order_id"]) ? (int)$data["order_id"] : null;
$subscriptionId     = isset($data["subscription_id"]) ? (int)$data["subscription_id"] : null;
$razorpayOrderId    = trim($data["razorpay_order_id"] ?? "");
$razorpayPaymentId  = trim($data["razorpay_payment_id"] ?? "");
$razorpaySignature  = trim($data["razorpay_signature"] ?? "");

if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "razorpay_order_id, razorpay_payment_id, and razorpay_signature are required"
    ]);
    exit;
}

// Verify cryptographic signature
$isValid = RazorpayHelper::verifySignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);

if (!$isValid) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid payment signature verification failed"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($orderId) {
        $stmt = $pdo->prepare("
            SELECT id, status, total_amount
            FROM orders
            WHERE id = ? AND customer_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId, $user->id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order not found");
        }

        // Update order status to CONFIRMED and payment fields
        try {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET
                    status = 'CONFIRMED',
                    payment_status = 'PAID',
                    payment_method = 'ONLINE',
                    razorpay_order_id = ?,
                    razorpay_payment_id = ?,
                    razorpay_signature = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $razorpayOrderId,
                $razorpayPaymentId,
                $razorpaySignature,
                $orderId
            ]);
        } catch (PDOException $e) {
            // Fallback for original schema if migration not yet applied
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'CONFIRMED'
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
        }

        // Update payment audit table if exists
        try {
            $stmt = $pdo->prepare("
                UPDATE payments
                SET status = 'SUCCESS', razorpay_payment_id = ?
                WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$razorpayPaymentId, $razorpayOrderId]);
        } catch (PDOException $e) {}

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Payment verified successfully. Order confirmed!",
            "order_id" => $orderId,
            "status" => "CONFIRMED",
            "payment_status" => "PAID",
            "razorpay_payment_id" => $razorpayPaymentId
        ]);

    } elseif ($subscriptionId) {
        $stmt = $pdo->prepare("
            SELECT id, status, total_amount
            FROM subscriptions
            WHERE id = ? AND customer_id = ?
            LIMIT 1
        ");
        $stmt->execute([$subscriptionId, $user->id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            throw new Exception("Subscription not found");
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE subscriptions
                SET
                    payment_status = 'PAID',
                    razorpay_order_id = ?,
                    razorpay_payment_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $razorpayOrderId,
                $razorpayPaymentId,
                $subscriptionId
            ]);
        } catch (PDOException $e) {}

        try {
            $stmt = $pdo->prepare("
                UPDATE payments
                SET status = 'SUCCESS', razorpay_payment_id = ?
                WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$razorpayPaymentId, $razorpayOrderId]);
        } catch (PDOException $e) {}

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Payment verified successfully for subscription!",
            "subscription_id" => $subscriptionId,
            "payment_status" => "PAID",
            "razorpay_payment_id" => $razorpayPaymentId
        ]);

    } else {
        throw new Exception("Either order_id or subscription_id must be provided");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

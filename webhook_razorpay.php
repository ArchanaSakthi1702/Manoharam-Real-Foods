<?php

header("Content-Type: application/json");

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/razorpay_helper.php';

$payload = file_get_contents("php://input");
$headers = getallheaders();
$webhookSignature = $headers['X-Razorpay-Signature'] ?? $headers['x-razorpay-signature'] ?? '';

// You can configure RAZORPAY_WEBHOOK_SECRET in .env
$webhookSecret = $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '';

if (!empty($webhookSecret) && !empty($webhookSignature)) {
    $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
    if (!hash_equals($expectedSignature, $webhookSignature)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid webhook signature"]);
        exit;
    }
}

$data = json_decode($payload, true);
$event = $data['event'] ?? '';

try {
    if ($event === 'payment.captured' || $event === 'order.paid') {
        $payment = $data['payload']['payment']['entity'] ?? [];
        $orderId = $payment['order_id'] ?? null;
        $paymentId = $payment['id'] ?? null;

        if ($orderId) {
            // Update orders if matches razorpay_order_id
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'CONFIRMED', payment_status = 'PAID', razorpay_payment_id = ?
                WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$paymentId, $orderId]);

            // Update subscriptions if matches
            $stmt = $pdo->prepare("
                UPDATE subscriptions
                SET payment_status = 'PAID', razorpay_payment_id = ?
                WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$paymentId, $orderId]);

            // Update payments table
            $stmt = $pdo->prepare("
                UPDATE payments
                SET status = 'SUCCESS', razorpay_payment_id = ?
                WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$paymentId, $orderId]);
        }
    } elseif ($event === 'payment.failed') {
        $payment = $data['payload']['payment']['entity'] ?? [];
        $orderId = $payment['order_id'] ?? null;
        if ($orderId) {
            $stmt = $pdo->prepare("
                UPDATE orders SET payment_status = 'FAILED' WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$orderId]);

            $stmt = $pdo->prepare("
                UPDATE payments SET status = 'FAILED' WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$orderId]);
        }
    }

    echo json_encode(["status" => "ok"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

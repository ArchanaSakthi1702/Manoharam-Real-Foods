<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

class RazorpayHelper
{
    public static function getKeyId(): string
    {
        return $_ENV['RAZOR_PAY_KEY'] ?? 'rzp_test_THLM79p8SJRLtA';
    }

    public static function getKeySecret(): string
    {
        return $_ENV['RAZOR_PAY_SECRET'] ?? 'scF0RKEAul5SeZwy3VGwGAo7';
    }

    public static function getApi(): Api
    {
        return new Api(self::getKeyId(), self::getKeySecret());
    }

    public static function createOrder(float $amountInRupees, string $receipt, array $notes = []): array
    {
        $api = self::getApi();

        $amountInPaisa = (int)round($amountInRupees * 100);

        $orderData = [
            'receipt'         => $receipt,
            'amount'          => $amountInPaisa,
            'currency'        => 'INR',
            'payment_capture' => 1,
            'notes'           => $notes
        ];

        $razorpayOrder = $api->order->create($orderData);

        return [
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount'            => $razorpayOrder['amount'],
            'amount_in_rupees'  => $amountInRupees,
            'currency'          => $razorpayOrder['currency'],
            'key'               => self::getKeyId(),
            'status'            => $razorpayOrder['status']
        ];
    }

    public static function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $api = self::getApi();

        $attributes = [
            'razorpay_order_id'   => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature'  => $signature
        ];

        try {
            $api->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }
}

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
require_once "../helpers/mail_helper.php";
require_once "../helpers/razorpay_helper.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$deliveryAddressInput = trim($data["delivery_address"] ?? "");
$paymentMethod = strtoupper(trim($data["payment_method"] ?? "COD"));

if (!in_array($paymentMethod, ["COD", "ONLINE"])) {
    $paymentMethod = "COD";
}

try {
    $pdo->beginTransaction();

    // --------------------------------------------------
    // Get customer details
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT name, email, phone, address
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user->id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception("Customer not found");
    }

    $finalAddress = !empty($deliveryAddressInput) ? $deliveryAddressInput : ($customer["address"] ?? "");

    // --------------------------------------------------
    // Find cart
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT id
        FROM carts
        WHERE customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user->id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        throw new Exception("Cart not found");
    }

    // --------------------------------------------------
    // Get cart items
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT
            ci.product_id,
            p.name,
            ci.quantity,
            p.selling_price
        FROM cart_items ci
        INNER JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = ?
          AND p.status = 'ACTIVE'
    ");
    $stmt->execute([$cart["id"]]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($items) === 0) {
        throw new Exception("Cart is empty");
    }

    // --------------------------------------------------
    // Calculate total amount
    // --------------------------------------------------
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += ($item["quantity"] * $item["selling_price"]);
    }

    // --------------------------------------------------
    // Check and decrease inventory (FEFO)
    // --------------------------------------------------
    $batchDeductions = [];

    foreach ($items as $item) {
        $required_quantity = (float)$item["quantity"];

        if ($required_quantity <= 0) {
            throw new Exception("Invalid quantity for product: " . $item["name"]);
        }

        $stmt = $pdo->prepare("
            SELECT id, remaining_quantity
            FROM inventory_batches
            WHERE product_id = ?
              AND remaining_quantity > 0
              AND expiry_date >= CURDATE()
            ORDER BY expiry_date ASC, id ASC
            FOR UPDATE
        ");
        $stmt->execute([$item["product_id"]]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $available_quantity = 0;
        foreach ($batches as $batch) {
            $available_quantity += (float)$batch["remaining_quantity"];
        }

        if ($available_quantity < $required_quantity) {
            throw new Exception(
                "Insufficient stock for product: " . $item["name"] .
                ". Available: " . $available_quantity .
                ", Requested: " . $required_quantity
            );
        }

        $remaining_to_deduct = $required_quantity;

        foreach ($batches as $batch) {
            if ($remaining_to_deduct <= 0) {
                break;
            }

            $batch_available = (float)$batch["remaining_quantity"];
            $deduct_quantity = min($batch_available, $remaining_to_deduct);

            $stmt = $pdo->prepare("
                UPDATE inventory_batches
                SET remaining_quantity = remaining_quantity - ?
                WHERE id = ?
            ");
            $stmt->execute([$deduct_quantity, $batch["id"]]);

            $batchDeductions[] = [
                "product_id" => $item["product_id"],
                "batch_id"   => $batch["id"],
                "quantity"   => $deduct_quantity
            ];

            $remaining_to_deduct -= $deduct_quantity;
        }
    }

    // --------------------------------------------------
    // Create order
    // --------------------------------------------------
    $initialStatus = ($paymentMethod === 'ONLINE') ? 'PENDING' : 'CONFIRMED';
    $paymentStatus = ($paymentMethod === 'ONLINE') ? 'PENDING' : 'PENDING';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                customer_id,
                delivery_address,
                total_amount,
                payment_method,
                payment_status,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user->id,
            $finalAddress,
            $total_amount,
            $paymentMethod,
            $paymentStatus,
            $initialStatus
        ]);
    } catch (PDOException $pe) {
        // Fallback for original schema
        $stmt = $pdo->prepare("
            INSERT INTO orders (customer_id, total_amount, status)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user->id, $total_amount, $initialStatus]);
    }

    $order_id = $pdo->lastInsertId();

    // --------------------------------------------------
    // Create order items
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $stmt->execute([
            $order_id,
            $item["product_id"],
            $item["quantity"],
            $item["selling_price"]
        ]);
    }

    // --------------------------------------------------
    // Record batch deductions for restitution on cancellation
    // --------------------------------------------------
    foreach ($batchDeductions as $bd) {
        try {
            $stmtDeduct = $pdo->prepare("
                INSERT INTO order_batch_deductions (order_id, product_id, batch_id, quantity)
                VALUES (?, ?, ?, ?)
            ");
            $stmtDeduct->execute([
                $order_id,
                $bd["product_id"],
                $bd["batch_id"],
                $bd["quantity"]
            ]);
        } catch (PDOException $e) {}
    }

    // --------------------------------------------------
    // Clear cart items
    // --------------------------------------------------
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cart["id"]]);

    $pdo->commit();

    // --------------------------------------------------
    // If ONLINE payment, prepare Razorpay order
    // --------------------------------------------------
    $razorpayData = null;
    if ($paymentMethod === 'ONLINE') {
        try {
            $razorpayData = RazorpayHelper::createOrder(
                (float)$total_amount,
                "rcpt_order_" . $order_id,
                ["order_id" => $order_id, "customer_id" => $user->id]
            );

            try {
                $stmt = $pdo->prepare("
                    UPDATE orders
                    SET razorpay_order_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$razorpayData["razorpay_order_id"], $order_id]);
            } catch (PDOException $e) {}

        } catch (Exception $re) {
            // Log but don't fail order creation
            error_log("Razorpay order generation warning: " . $re->getMessage());
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Order placed successfully",
        "order_id" => (int)$order_id,
        "payment_method" => $paymentMethod,
        "status" => $initialStatus,
        "total_amount" => number_format($total_amount, 2, '.', ''),
        "delivery_address" => $finalAddress,
        "razorpay" => $razorpayData
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

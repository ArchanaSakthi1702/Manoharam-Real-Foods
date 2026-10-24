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
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

try {

    $pdo->beginTransaction();

    // --------------------------------------------------
    // Get customer details
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            name,
            email,
            phone
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$user->id]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception("Customer not found");
    }


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
        INNER JOIN products p
            ON p.id = ci.product_id
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

        $total_amount += (
            $item["quantity"] *
            $item["selling_price"]
        );
    }


    // --------------------------------------------------
    // Check and decrease inventory
    //
    // FEFO:
    // First Expiry, First Out
    // --------------------------------------------------

    foreach ($items as $item) {

        $required_quantity = (int)$item["quantity"];

        if ($required_quantity <= 0) {
            throw new Exception(
                "Invalid quantity for product: " . $item["name"]
            );
        }


        // Get available batches.
        // FOR UPDATE locks these rows until the transaction
        // is committed or rolled back.
        $stmt = $pdo->prepare("
            SELECT
                id,
                remaining_quantity
            FROM inventory_batches
            WHERE product_id = ?
              AND remaining_quantity > 0
              AND expiry_date >= CURDATE()
            ORDER BY expiry_date ASC, id ASC
            FOR UPDATE
        ");

        $stmt->execute([
            $item["product_id"]
        ]);

        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);


        // --------------------------------------------------
        // Calculate total available stock
        // --------------------------------------------------

        $available_quantity = 0;

        foreach ($batches as $batch) {

            $available_quantity += (int)$batch["remaining_quantity"];
        }


        // --------------------------------------------------
        // Not enough stock
        // --------------------------------------------------

        if ($available_quantity < $required_quantity) {

            throw new Exception(
                "Insufficient stock for product: " . $item["name"] .
                ". Available: " . $available_quantity .
                ", Requested: " . $required_quantity
            );
        }


        // --------------------------------------------------
        // Deduct stock using FEFO
        // --------------------------------------------------

        $remaining_to_deduct = $required_quantity;

        foreach ($batches as $batch) {

            if ($remaining_to_deduct <= 0) {
                break;
            }

            $batch_available = (int)$batch["remaining_quantity"];

            $deduct_quantity = min(
                $batch_available,
                $remaining_to_deduct
            );


            // Decrease batch stock
            $stmt = $pdo->prepare("
                UPDATE inventory_batches
                SET remaining_quantity = remaining_quantity - ?
                WHERE id = ?
            ");

            $stmt->execute([
                $deduct_quantity,
                $batch["id"]
            ]);


            $remaining_to_deduct -= $deduct_quantity;
        }
    }


    // --------------------------------------------------
    // Create order
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        INSERT INTO orders (
            customer_id,
            total_amount,
            status
        )
        VALUES (
            ?, ?, 'PENDING'
        )
    ");

    $stmt->execute([
        $user->id,
        $total_amount
    ]);

    $order_id = $pdo->lastInsertId();


    // --------------------------------------------------
    // Create order items
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            price
        )
        VALUES (
            ?, ?, ?, ?
        )
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
    // Clear cart items
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        DELETE FROM cart_items
        WHERE cart_id = ?
    ");

    $stmt->execute([
        $cart["id"]
    ]);


    // --------------------------------------------------
    // Commit transaction
    // --------------------------------------------------

    $pdo->commit();


    // --------------------------------------------------
    // Send admin email
    //
    // IMPORTANT:
    // Send email AFTER commit so a mail failure does not
    // rollback the successful order.
    // --------------------------------------------------

    /*
    try {

        MailHelper::sendAdminOrderNotification(
            $order_id,
            $customer,
            $items,
            $total_amount
        );

    } catch (Exception $mailError) {

        error_log(
            "Order notification mail failed: " .
            $mailError->getMessage()
        );
    }
    */


    // --------------------------------------------------
    // Response
    // --------------------------------------------------

    echo json_encode([
        "success" => true,
        "message" => "Order placed successfully",
        "order_id" => $order_id,
        "total_amount" => $total_amount
    ]);


} catch (Exception $e) {

    // Rollback only if a transaction is still active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
